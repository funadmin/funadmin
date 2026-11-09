<?php
declare(strict_types=1);
namespace app\cms\controller;
use support\annotation\route\{Route,RouteGroup};
use support\{Request,Response};
use app\cms\service\PublicSite;
#[RouteGroup('/cms')]
final class Index
{
    #[Route('', ['GET','HEAD'])]
    #[Route('/', ['GET','HEAD'])]
    // Reserve member and public form API paths so the public catch-all cannot shadow their routes.
    #[Route('/{path:(?!(?:member|forms)(?:/|$)).+}', ['GET','HEAD'])]
    public function index(Request $request,string $path=''): Response { return (new PublicSite())->handle($request); }
}
