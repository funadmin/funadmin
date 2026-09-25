<?php

declare(strict_types=1);

namespace app\admin\service\plugin\market;

use app\common\model\Member;
use app\market\service\MarketOrderService;
use app\market\service\MarketProtocol;
use app\market\service\MarketSettings;
use app\market\service\MarketSigner;
use app\market\service\MarketStatsService;
use app\market\service\payment\PaymentConfig;
use RuntimeException;
use think\facade\Db;

/** 插件市场后台：分类、插件、版本发布、授权与签名密钥。 */
final class MarketAdminService
{
    private const VERSION_STATUSES = ['draft', 'published', 'withdrawn'];

    public function overview(): array
    {
        return [
            'plugins' => (int) Db::name('market_plugin')->count(),
            'publishedVersions' => (int) Db::name('market_version')->where('status', 'published')->count(),
            'downloads' => (int) Db::name('market_download')->count(),
            'grants' => (int) Db::name('market_grant')->where('status', 1)->count(),
            'paidOrders' => (int) Db::name('market_order')->where('status', 'paid')->count(),
            'revenue' => (int) Db::name('market_order')->where('status', 'paid')->sum('paid_amount'),
            'activeInstalls' => (int) Db::name('market_site_plugin')->where('state', '<>', 'uninstalled')->count(),
            'signing' => $this->signingStatus(),
            'payment' => PaymentConfig::enabledChannels(),
        ];
    }

    public function signingStatus(): array
    {
        $publicUrl = MarketSettings::publicUrl();
        return [
            'sodium' => function_exists('sodium_crypto_sign_detached'),
            'keySource' => MarketSigner::keySource(),
            'publicKey' => MarketSigner::publicKey(),
            'publicUrl' => $publicUrl,
            'https' => str_starts_with(strtolower($publicUrl), 'https://'),
        ];
    }

    public function generateKey(): array
    {
        MarketSigner::generate();
        return $this->signingStatus();
    }

    // ---- 分类 ----

    public function categories(): array
    {
        $counts = Db::name('market_plugin')->group('category_id')->column('count(*)', 'category_id');
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'sort' => (int) $row['sort'],
            'status' => (int) $row['status'],
            'pluginCount' => (int) ($counts[$row['id']] ?? 0),
        ], Db::name('market_category')->order(['sort' => 'desc', 'id' => 'asc'])->select()->toArray());
    }

    public function saveCategory(?int $id, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 50) {
            throw new RuntimeException('分类名称不能为空且不超过 50 个字符');
        }
        $duplicate = Db::name('market_category')->where('name', $name);
        if ($id !== null) {
            $duplicate->where('id', '<>', $id);
        }
        if ($duplicate->count() > 0) {
            throw new RuntimeException('分类名称已存在');
        }
        $data = ['name' => $name, 'sort' => (int) ($input['sort'] ?? 0), 'status' => (int) (bool) ($input['status'] ?? 1), 'updated_at' => $this->now()];
        if ($id === null) {
            Db::name('market_category')->insert($data + ['created_at' => $this->now()]);
            return;
        }
        $this->requireRow('market_category', $id, '分类不存在');
        Db::name('market_category')->where('id', $id)->update($data);
    }

    public function deleteCategory(int $id): void
    {
        $this->requireRow('market_category', $id, '分类不存在');
        if (Db::name('market_plugin')->where('category_id', $id)->count() > 0) {
            throw new RuntimeException('分类下仍有插件，请先调整插件分类');
        }
        Db::name('market_category')->where('id', $id)->delete();
    }

    // ---- 插件 ----

    public function plugins(string $keyword, int $page, int $pageSize): array
    {
        $build = static function () use ($keyword) {
            $query = Db::name('market_plugin');
            $keyword = trim($keyword);
            if ($keyword !== '') {
                $like = '%' . addcslashes($keyword, '%_\\') . '%';
                $query->where(static function ($where) use ($like): void {
                    $where->whereLike('code', $like)->whereOr('name', 'like', $like);
                });
            }
            return $query;
        };
        $total = $build()->count();
        $rows = $build()->order(['sort' => 'desc', 'id' => 'desc'])->page($page, $pageSize)->select()->toArray();
        $ids = array_column($rows, 'id');
        $versions = $ids === [] ? [] : Db::name('market_version')->whereIn('plugin_id', $ids)->field('plugin_id,code_version,status,download_count')->select()->toArray();
        $categories = Db::name('market_category')->column('name', 'id');
        $list = array_map(function (array $row) use ($versions, $categories): array {
            $own = array_values(array_filter($versions, static fn (array $version): bool => (int) $version['plugin_id'] === (int) $row['id']));
            $published = array_values(array_filter($own, static fn (array $version): bool => $version['status'] === 'published'));
            usort($published, static fn (array $left, array $right): int => version_compare($right['code_version'], $left['code_version']));
            return $this->pluginRow($row) + [
                'categoryName' => (string) ($categories[$row['category_id']] ?? ''),
                'latestVersion' => (string) ($published[0]['code_version'] ?? ''),
                'versionCount' => count($own),
                'draftCount' => count(array_filter($own, static fn (array $version): bool => $version['status'] === 'draft')),
                'downloads' => array_sum(array_map(static fn (array $version): int => (int) $version['download_count'], $own)),
            ];
        }, $rows);
        return ['list' => $list, 'total' => (int) $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function updatePlugin(int $id, array $input): void
    {
        $this->requireRow('market_plugin', $id, '插件不存在');
        $name = trim((string) ($input['name'] ?? ''));
        $licenseType = (string) ($input['licenseType'] ?? 'free');
        $categoryId = (int) ($input['categoryId'] ?? 0);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('插件名称不能为空且不超过 100 个字符');
        }
        if (!in_array($licenseType, ['free', 'grant'], true)) {
            throw new RuntimeException('授权方式无效');
        }
        if ($categoryId > 0) {
            $this->requireRow('market_category', $categoryId, '分类不存在');
        }
        $pricePerpetual = $this->cents($input['pricePerpetual'] ?? 0, '买断价');
        $priceYearly = $this->cents($input['priceYearly'] ?? 0, '年费');
        if ($licenseType === 'free') {
            $pricePerpetual = $priceYearly = 0;
        }
        $cover = trim((string) ($input['cover'] ?? ''));
        if ($cover !== '' && (strlen($cover) > 500 || preg_match('#^(https://|/)[^\s"\'<>]*$#i', $cover) !== 1 || str_starts_with($cover, '//'))) {
            throw new RuntimeException('封面必须是 https 地址或站内 / 开头的路径');
        }
        $homepage = trim((string) ($input['homepage'] ?? ''));
        if ($homepage !== '' && (strlen($homepage) > 500 || preg_match('#^https?://[^\s"\'<>]+$#i', $homepage) !== 1)) {
            throw new RuntimeException('项目主页必须是 http(s) 地址');
        }
        Db::name('market_plugin')->where('id', $id)->update([
            'price_perpetual' => $pricePerpetual,
            'price_yearly' => $priceYearly,
            'cover' => $cover,
            'homepage' => $homepage,
            'name' => $name,
            'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 1000),
            'author' => mb_substr(trim((string) ($input['author'] ?? '')), 0, 100),
            'category_id' => max(0, $categoryId),
            'license_type' => $licenseType,
            'status' => (int) (bool) ($input['status'] ?? 1),
            'sort' => (int) ($input['sort'] ?? 0),
            'updated_at' => $this->now(),
        ]);
    }

    public function deletePlugin(int $id): void
    {
        $this->requireRow('market_plugin', $id, '插件不存在');
        if (Db::name('market_version')->where('plugin_id', $id)->where('status', 'published')->count() > 0) {
            throw new RuntimeException('插件仍有已发布版本，请先撤回全部版本或改为下架');
        }
        $paths = Db::name('market_version')->where('plugin_id', $id)->column('package_path');
        Db::transaction(static function () use ($id): void {
            Db::name('market_version')->where('plugin_id', $id)->delete();
            Db::name('market_grant')->where('plugin_id', $id)->delete();
            Db::name('market_plugin')->where('id', $id)->delete();
        });
        array_walk($paths, fn (string $path) => $this->removePackage($path));
    }

    // ---- 版本 ----

    public function versions(int $pluginId): array
    {
        $this->requireRow('market_plugin', $pluginId, '插件不存在');
        $rows = Db::name('market_version')->where('plugin_id', $pluginId)->select()->toArray();
        usort($rows, static fn (array $left, array $right): int => version_compare((string) $right['code_version'], (string) $left['code_version']));
        return array_map(fn (array $row): array => $this->versionRow($row), $rows);
    }

    /** 上传即检查并签名，生成草稿；发布前可重复上传同一版本号覆盖草稿。 */
    public function upload(string $archive, string $changelog): array
    {
        if (!MarketSigner::available()) {
            throw new RuntimeException('尚未配置 Ed25519 签名密钥，请先在「市场设置」中生成');
        }
        $meta = (new MarketPackageInspector())->inspect($archive);
        $plugin = Db::name('market_plugin')->where('code', $meta['code'])->find();
        $existing = $plugin ? Db::name('market_version')->where('plugin_id', $plugin['id'])->where('code_version', $meta['code_version'])->find() : null;
        if ($existing && $existing['status'] !== 'draft') {
            throw new RuntimeException('版本 ' . $meta['code_version'] . ' 已发布过，版本号不可复用，请提升 plugin.json 中的 version');
        }
        $signature = MarketSigner::sign($meta);
        $relative = 'packages/' . $meta['code'] . '/' . $meta['code'] . '-' . $meta['code_version'] . '-' . substr($meta['sha256'], 0, 12) . '.zip';
        $target = MarketSettings::storagePath($relative);
        MarketSettings::ensureDirectory(dirname($target));
        if (!copy($archive, $target) || hash_file('sha256', $target) !== $meta['sha256']) {
            @unlink($target);
            throw new RuntimeException('无法保存插件包');
        }
        try {
            $versionId = Db::transaction(function () use ($plugin, $existing, $meta, $signature, $relative, $changelog): int {
                $pluginId = $plugin ? (int) $plugin['id'] : (int) Db::name('market_plugin')->insertGetId([
                    'code' => $meta['code'],
                    'name' => mb_substr($meta['name'], 0, 100),
                    'description' => mb_substr($meta['description'], 0, 1000),
                    'author' => mb_substr($meta['author'], 0, 100),
                    'license_type' => 'free',
                    'status' => 1,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);
                $data = [
                    'plugin_id' => $pluginId,
                    'code_version' => $meta['code_version'],
                    'changelog' => mb_substr($changelog, 0, 20000),
                    'requires' => json_encode($meta['requires'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sha256' => $meta['sha256'],
                    'size' => $meta['size'],
                    'tree_hash' => $meta['tree_hash'],
                    'database_capability' => $meta['database_capability'],
                    'app_enabled' => (int) $meta['applications']['app'],
                    'admin_enabled' => (int) $meta['applications']['admin'],
                    'signature' => $signature,
                    'package_path' => $relative,
                    'status' => 'draft',
                    'updated_at' => $this->now(),
                ];
                if ($existing) {
                    Db::name('market_version')->where('id', $existing['id'])->update($data);
                    return (int) $existing['id'];
                }
                return (int) Db::name('market_version')->insertGetId($data + ['created_at' => $this->now()]);
            });
        } catch (\Throwable $exception) {
            @unlink($target);
            throw $exception;
        }
        if ($existing && $existing['package_path'] !== $relative) {
            $this->removePackage((string) $existing['package_path']);
        }
        return $this->versionRow(Db::name('market_version')->where('id', $versionId)->find());
    }

    public function updateVersion(int $id, array $input): void
    {
        $this->requireRow('market_version', $id, '版本不存在');
        Db::name('market_version')->where('id', $id)->update([
            'changelog' => mb_substr((string) ($input['changelog'] ?? ''), 0, 20000),
            'updated_at' => $this->now(),
        ]);
    }

    /** 发布前复核签名与包文件，防止密钥轮换或文件损坏后发布出客户端必然验签失败的版本。 */
    public function publishVersion(int $id): void
    {
        $row = $this->requireRow('market_version', $id, '版本不存在');
        if ($row['status'] === 'published') {
            return;
        }
        $plugin = $this->requireRow('market_plugin', (int) $row['plugin_id'], '插件不存在');
        $meta = [
            'code' => $plugin['code'],
            'code_version' => $row['code_version'],
            'database_capability' => $row['database_capability'],
            'sha256' => $row['sha256'],
            'size' => (int) $row['size'],
            'tree_hash' => $row['tree_hash'],
        ];
        if (!MarketSigner::verify($meta, (string) $row['signature'])) {
            throw new RuntimeException('版本签名与当前市场公钥不匹配，请重新上传该版本以重新签名');
        }
        $path = MarketSettings::storagePath((string) $row['package_path']);
        if (!is_file($path) || filesize($path) !== (int) $row['size'] || hash_file('sha256', $path) !== $row['sha256']) {
            throw new RuntimeException('插件包文件缺失或已损坏，请重新上传');
        }
        Db::name('market_version')->where('id', $id)->update([
            'status' => 'published',
            'published_at' => $row['published_at'] ?: $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    public function withdrawVersion(int $id): void
    {
        $row = $this->requireRow('market_version', $id, '版本不存在');
        if ($row['status'] !== 'published') {
            throw new RuntimeException('只有已发布的版本可以撤回');
        }
        Db::name('market_version')->where('id', $id)->update(['status' => 'withdrawn', 'updated_at' => $this->now()]);
    }

    public function republishVersion(int $id): void
    {
        $row = $this->requireRow('market_version', $id, '版本不存在');
        if ($row['status'] !== 'withdrawn') {
            throw new RuntimeException('只有已撤回的版本可以重新发布');
        }
        Db::name('market_version')->where('id', $id)->update(['status' => 'draft']);
        $this->publishVersion($id);
    }

    public function deleteVersion(int $id): void
    {
        $row = $this->requireRow('market_version', $id, '版本不存在');
        if ($row['status'] === 'published') {
            throw new RuntimeException('已发布版本不能删除，请先撤回');
        }
        if ((int) $row['download_count'] > 0) {
            throw new RuntimeException('该版本已有下载记录，只能撤回不能删除，以免客户端历史版本失去来源');
        }
        Db::name('market_version')->where('id', $id)->delete();
        $this->removePackage((string) $row['package_path']);
    }

    public function versionPackage(int $id): array
    {
        $row = $this->requireRow('market_version', $id, '版本不存在');
        $plugin = $this->requireRow('market_plugin', (int) $row['plugin_id'], '插件不存在');
        $path = MarketSettings::storagePath((string) $row['package_path']);
        if (!is_file($path)) {
            throw new RuntimeException('插件包文件不存在');
        }
        return ['path' => $path, 'name' => $plugin['code'] . '-' . $row['code_version'] . '.zip'];
    }

    // ---- 授权 ----

    public function grants(int $pluginId, int $page, int $pageSize): array
    {
        $build = static function () use ($pluginId) {
            $query = Db::name('market_grant')->alias('g')
                ->leftJoin('market_plugin p', 'p.id = g.plugin_id')
                ->leftJoin('member m', 'm.id = g.member_id');
            if ($pluginId > 0) {
                $query->where('g.plugin_id', $pluginId);
            }
            return $query;
        };
        $total = $build()->count();
        $rows = $build()->field('g.*, p.code as plugin_code, p.name as plugin_name, m.username, m.nickname')
            ->order('g.id', 'desc')->page($page, $pageSize)->select()->toArray();
        $now = $this->now();
        $list = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'pluginId' => (int) $row['plugin_id'],
            'pluginCode' => (string) ($row['plugin_code'] ?? ''),
            'pluginName' => (string) ($row['plugin_name'] ?? ''),
            'memberId' => (int) $row['member_id'],
            'username' => (string) ($row['username'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'expiresAt' => (string) ($row['expires_at'] ?? ''),
            'status' => (int) $row['status'],
            'active' => (int) $row['status'] === 1 && (($row['expires_at'] ?? null) === null || $row['expires_at'] > $now),
            'remark' => (string) $row['remark'],
            'source' => (string) ($row['source'] ?? 'admin'),
            'plan' => (string) ($row['plan'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
        ], $rows);
        return ['list' => $list, 'total' => (int) $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    public function saveGrant(?int $id, array $input): void
    {
        $expiresAt = trim((string) ($input['expiresAt'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) === false) {
            throw new RuntimeException('到期时间格式无效');
        }
        $data = [
            'expires_at' => $expiresAt === '' ? null : date('Y-m-d H:i:s', (int) strtotime($expiresAt)),
            'status' => (int) (bool) ($input['status'] ?? 1),
            'remark' => mb_substr(trim((string) ($input['remark'] ?? '')), 0, 255),
            'updated_at' => $this->now(),
        ];
        if ($id !== null) {
            $this->requireRow('market_grant', $id, '授权不存在');
            Db::name('market_grant')->where('id', $id)->update($data);
            return;
        }
        $pluginId = (int) ($input['pluginId'] ?? 0);
        $this->requireRow('market_plugin', $pluginId, '插件不存在');
        $account = trim((string) ($input['account'] ?? ''));
        $member = $account === '' ? null : Member::where(static function ($query) use ($account): void {
            $query->where('username', $account)->whereOr('email', $account)->whereOr('mobile', $account);
        })->field('id')->find();
        if (!$member) {
            throw new RuntimeException('未找到该会员账号（支持用户名、邮箱或手机号）');
        }
        $existing = Db::name('market_grant')->where('plugin_id', $pluginId)->where('member_id', $member['id'])->find();
        if ($existing) {
            Db::name('market_grant')->where('id', $existing['id'])->update($data);
            return;
        }
        Db::name('market_grant')->insert($data + ['plugin_id' => $pluginId, 'member_id' => (int) $member['id'], 'created_at' => $this->now()]);
    }

    public function deleteGrant(int $id): void
    {
        $this->requireRow('market_grant', $id, '授权不存在');
        Db::name('market_grant')->where('id', $id)->delete();
    }

    // ---- 订单 ----

    public function orders(array $filters, int $page, int $pageSize): array
    {
        (new MarketOrderService())->closeExpired();
        $build = static function () use ($filters) {
            $query = Db::name('market_order')->alias('o')
                ->leftJoin('market_plugin p', 'p.id = o.plugin_id')
                ->leftJoin('member m', 'm.id = o.member_id');
            $status = (string) ($filters['status'] ?? '');
            if (in_array($status, ['pending', 'paid', 'closed'], true)) {
                $query->where('o.status', $status);
            }
            $keyword = trim((string) ($filters['keyword'] ?? ''));
            if ($keyword !== '') {
                $like = '%' . addcslashes($keyword, '%_\\') . '%';
                $query->where(static function ($where) use ($keyword, $like): void {
                    $where->where('o.order_no', $keyword)->whereOr('o.trade_no', $keyword)->whereOr('m.username', 'like', $like)->whereOr('p.code', 'like', $like)->whereOr('p.name', 'like', $like);
                });
            }
            if ((int) ($filters['pluginId'] ?? 0) > 0) {
                $query->where('o.plugin_id', (int) $filters['pluginId']);
            }
            return $query;
        };
        $total = (int) $build()->count();
        $rows = $build()->field('o.*, p.code as plugin_code, p.name as plugin_name, m.username, m.nickname')
            ->order('o.id', 'desc')->page($page, $pageSize)->select()->toArray();
        return [
            'list' => array_map(fn (array $row): array => $this->orderRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'summary' => [
                'paidCount' => (int) $build()->where('o.status', 'paid')->count(),
                'paidAmount' => (int) $build()->where('o.status', 'paid')->sum('o.paid_amount'),
            ],
        ];
    }

    public function orderDetail(string $orderNo): array
    {
        $order = (new MarketOrderService())->find($orderNo);
        if ($order === null) {
            throw new RuntimeException('订单不存在');
        }
        $member = Db::name('member')->where('id', $order['member_id'])->field('username,nickname,email')->find() ?: [];
        $logs = Db::name('market_payment_log')->where('order_no', $orderNo)->order('id', 'desc')->limit(50)
            ->field('channel,event,verified,result,created_at')->select()->toArray();
        return $this->orderRow($order + $member) + ['logs' => array_map(static fn (array $log): array => [
            'channel' => (string) $log['channel'],
            'event' => (string) $log['event'],
            'verified' => (bool) $log['verified'],
            'result' => (string) $log['result'],
            'createdAt' => (string) $log['created_at'],
        ], $logs)];
    }

    /** 线下收款或渠道对账后人工入账：沿用与回调相同的幂等入账流程。 */
    public function confirmOrder(string $orderNo, string $remark, int $adminId): void
    {
        $remark = trim($remark);
        if ($remark === '') {
            throw new RuntimeException('请填写收款说明（例如转账流水号），便于对账');
        }
        $order = (new MarketOrderService())->find($orderNo) ?? throw new RuntimeException('订单不存在');
        if ($order['status'] === 'paid') {
            throw new RuntimeException('订单已支付');
        }
        $service = new MarketOrderService();
        $service->log($orderNo, 'manual', 'manual', true, 'admin#' . $adminId . ' ' . mb_substr($remark, 0, 200), []);
        Db::name('market_order')->where('order_no', $orderNo)->update(['status' => 'pending', 'expire_at' => date('Y-m-d H:i:s', time() + 600)]);
        $service->markPaid($orderNo, 'manual', '', (int) $order['amount'], '人工确认：' . $remark);
    }

    public function closeOrder(string $orderNo): void
    {
        $order = (new MarketOrderService())->find($orderNo) ?? throw new RuntimeException('订单不存在');
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('只有待支付订单可以关闭');
        }
        (new MarketOrderService())->close($orderNo, '管理员关闭');
    }

    // ---- 统计与支付设置 ----

    public function stats(int $days): array
    {
        return (new MarketStatsService())->overview($days);
    }

    public function paymentSettings(): array
    {
        return PaymentConfig::masked() + ['notifyUrls' => [
            'alipay' => MarketSettings::publicUrl() . '/pay/notify/alipay',
            'wechat' => MarketSettings::publicUrl() . '/pay/notify/wechat',
        ]];
    }

    public function savePaymentSettings(array $input): array
    {
        PaymentConfig::save($input);
        return $this->paymentSettings();
    }

    private function orderRow(array $row): array
    {
        return [
            'orderNo' => (string) $row['order_no'],
            'pluginCode' => (string) ($row['plugin_code'] ?? ''),
            'pluginName' => (string) ($row['plugin_name'] ?? ''),
            'memberId' => (int) $row['member_id'],
            'username' => (string) ($row['username'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'plan' => (string) $row['plan'],
            'planText' => MarketOrderService::PLANS[$row['plan']] ?? (string) $row['plan'],
            'amount' => (int) $row['amount'],
            'paidAmount' => (int) $row['paid_amount'],
            'status' => (string) $row['status'],
            'channel' => (string) $row['channel'],
            'channelText' => MarketOrderService::CHANNELS[$row['channel']] ?? (string) $row['channel'],
            'tradeNo' => (string) ($row['trade_no'] ?? ''),
            'remark' => (string) ($row['remark'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'paidAt' => (string) ($row['paid_at'] ?? ''),
            'expireAt' => (string) ($row['expire_at'] ?? ''),
            'clientIp' => (string) ($row['client_ip'] ?? ''),
        ];
    }

    /** 金额以「元」提交、以「分」存储，避免浮点误差。 */
    private function cents(mixed $value, string $label): int
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '0') {
            return 0;
        }
        if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $text)) {
            throw new RuntimeException($label . '格式无效（最多两位小数，不超过 9999999 元）');
        }
        [$yuan, $fraction] = array_pad(explode('.', $text, 2), 2, '0');
        return (int) $yuan * 100 + (int) str_pad($fraction, 2, '0');
    }

    // ---- 工具 ----

    private function pluginRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'author' => (string) $row['author'],
            'categoryId' => (int) $row['category_id'],
            'licenseType' => (string) $row['license_type'],
            'pricePerpetual' => (int) ($row['price_perpetual'] ?? 0),
            'priceYearly' => (int) ($row['price_yearly'] ?? 0),
            'cover' => (string) ($row['cover'] ?? ''),
            'homepage' => (string) ($row['homepage'] ?? ''),
            'status' => (int) $row['status'],
            'sort' => (int) $row['sort'],
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function versionRow(array $row): array
    {
        $requires = json_decode((string) ($row['requires'] ?? ''), true);
        return [
            'id' => (int) $row['id'],
            'pluginId' => (int) $row['plugin_id'],
            'version' => (string) $row['code_version'],
            'changelog' => (string) ($row['changelog'] ?? ''),
            'requires' => is_array($requires) ? $requires : [],
            'sha256' => (string) $row['sha256'],
            'size' => (int) $row['size'],
            'treeHash' => (string) $row['tree_hash'],
            'databaseCapability' => (string) $row['database_capability'],
            'applications' => ['app' => (bool) $row['app_enabled'], 'admin' => (bool) $row['admin_enabled']],
            'signed' => (string) $row['signature'] !== '',
            'status' => in_array($row['status'], self::VERSION_STATUSES, true) ? $row['status'] : 'draft',
            'downloadCount' => (int) $row['download_count'],
            'publishedAt' => (string) ($row['published_at'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'packageFormat' => MarketProtocol::PACKAGE_FORMAT,
        ];
    }

    private function requireRow(string $table, int $id, string $message): array
    {
        $row = $id > 0 ? Db::name($table)->where('id', $id)->find() : null;
        if (!$row) {
            throw new RuntimeException($message);
        }
        return $row;
    }

    private function removePackage(string $relative): void
    {
        if (!preg_match('#^packages/[a-z][a-z0-9]*/[A-Za-z0-9._+-]+\.zip$#', $relative)) {
            return;
        }
        $path = MarketSettings::storagePath($relative);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
