<?php

use app\http\middleware\InstallMiddleware;
use think\facade\Route;
use think\Response;

/**
 * 系统默认路由配置
 */
Route::get('install/index', 'InstallController/index');//安装程序
Route::post('install/index', 'InstallController/index');//安装程序
Route::get('install/compiler', 'InstallController/swooleCompiler');//swooleCompiler安装提示
Route::get('upgrade/index', 'UpgradeController/index');
Route::get('up', 'UpgradeVersionController/index');
Route::get('up/render', 'UpgradeVersionController/render');
Route::get('upgrade/upgrade', 'UpgradeController/upgrade');

Route::group('/', function () {
    Route::group('install', function () {
        Route::miss(function () {
            return view(app()->getRootPath() . 'public' . DS . 'install');
        });
    });

	//平台
    Route::group(config('admin.admin_prefix'), function () {
        Route::miss(function () {
            $pathInfo = request()->pathinfo();
            $pathInfoArr = explode('/', $pathInfo);
            $admin = $pathInfoArr[0] ?? '';
            if ($admin === config('admin.admin_prefix')) {
                return view(app()->getRootPath() . 'public' . DS . 'system.html');
            } else {
                return Response::create()->code(404);
            }
        });
    });
	//客服
    Route::group(config('admin.kefu_prefix'), function () {
        Route::miss(function () {
            $pathInfo = request()->pathinfo();
            $pathInfoArr = explode('/', $pathInfo);
            $admin = $pathInfoArr[0] ?? '';
            if ($admin === config('admin.kefu_prefix')) {
                return view(app()->getRootPath() . 'public' . DS . 'system.html');
            } else {
                return Response::create()->code(404);
            }
        });
    });
	//移动端h5
    Route::group('pages', function () {
        Route::miss(function () {
            $pathInfo = request()->pathinfo();
            $pathInfoArr = explode('/', $pathInfo);
            $admin = $pathInfoArr[0] ?? '';
            if ('pages' === $admin) {
                return view(app()->getRootPath() . 'public' . DS . 'index.html');
            } else {
                return Response::create()->code(404);
            }
        });
    });
	//pc
    Route::group('home', function () {
        Route::miss(function () {
            if (request()->isMobile()) {
                return redirect(app()->route->buildUrl('/'));
            } else {
                return view(app()->getRootPath() . 'public' . DS . 'home' . DS . 'index.html');
            }
        });
    });
	//供应商
	Route::group(config('admin.supplier_prefix'), function () {
		Route::miss(function () {
			$pathInfo = request()->pathinfo();
			$pathInfoArr = explode('/', $pathInfo);
			$admin = $pathInfoArr[0] ?? '';
			if ($admin === config('admin.supplier_prefix')) {
				return view(app()->getRootPath() . 'public' . DS . 'supplier.html');
			} else {
				return Response::create()->code(404);
			}
		});
	});
	//门店
    Route::group(config('admin.store_prefix'), function () {
        Route::miss(function () {
            $pathInfo = request()->pathinfo();
            $pathInfoArr = explode('/', $pathInfo);
            $admin = $pathInfoArr[0] ?? '';
            if ($admin === config('admin.store_prefix')) {
                return view(app()->getRootPath() . 'public' . DS . 'store.html');
            } else {
                return Response::create()->code(404);
            }
        });
    });
	//收银台
	Route::group(config('admin.cashier_prefix'), function () {
		Route::miss(function () {
			$pathInfo = request()->pathinfo();
			$pathInfoArr = explode('/', $pathInfo);
			$admin = $pathInfoArr[0] ?? '';
			if ($admin === config('admin.cashier_prefix')) {
				return view(app()->getRootPath() . 'public' . DS . 'cashier.html');
			} else {
				return Response::create()->code(404);
			}
		});
	});


    Route::miss(function () {
        if (!request()->isMobile() && is_dir(app()->getRootPath() . 'public' . DS . 'home') && !request()->get('type')) {
			if (file_exists(app()->getRootPath() . 'public' . DS . 'home'. DS . 'index.html')) {
				return view(app()->getRootPath() . 'public' . DS . 'home' . DS . 'index.html');
			} else {
				return view(app()->getRootPath() . 'public' . DS . 'index.html');
			}
        } else {
            return view(app()->getRootPath() . 'public' . DS . 'index.html');
        }
    });

})->middleware(InstallMiddleware::class);
