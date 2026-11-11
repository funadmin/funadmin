<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\admin\development\service\BusinessSoftDeleteUpgradeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 已发布业务表的软删除升级：补 deleted_at 索引、deleted_token（及 row_no），重建唯一键；可重复执行。 */
#[AsCommand(name: 'business:soft-delete-upgrade')]
final class BusinessSoftDeleteUpgrade extends Command
{
    private const LABELS = ['upgraded' => '已升级', 'planned' => '计划', 'current' => '无需升级', 'skipped' => '跳过', 'refused' => '拒绝'];

    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('business:soft-delete-upgrade')
            ->setDescription('已发布业务表按软删除规则升级（deleted_at 索引、deleted_token、唯一键重建）')
            ->addOption('table', null, Option::VALUE_REQUIRED, '只处理这张业务表（逻辑名或带前缀的物理名）', '')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只列出计划，不执行');
    }

    protected function execute(Input $input, Output $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        try {
            $results = (new BusinessSoftDeleteUpgradeService($this->projectRoot ?? base_path()))
                ->upgrade(trim((string) $input->getOption('table')), $dryRun);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());
            return 1;
        }
        if ($results === []) {
            $output->writeln('没有已发布的业务模块表');
        }
        $failed = false;
        foreach ($results as $result) {
            $failed = $failed || $result['status'] === 'refused';
            $output->writeln(sprintf('[%s] %s（业务模块 %s）：%s', self::LABELS[$result['status']] ?? $result['status'], $result['table'], $result['module'], $result['message']));
            foreach ($result['sql'] as $sql) $output->writeln('  ' . $sql . ';');
            if ($result['backfill'] > 0) $output->writeln('  回填已删除行的 deleted_token：' . $result['backfill'] . ' 行');
            if ($result['migration'] !== null) $output->writeln('  插件升级迁移：' . $result['migration']);
            foreach ($result['notes'] as $note) $output->writeln('  注意：' . $note);
        }
        if ($dryRun) $output->writeln('dry-run：没有执行任何变更');
        return $failed ? 1 : 0;
    }
}
