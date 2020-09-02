<?php

namespace App\Providers;

use App\Repositories\CartRepository;
use App\Repositories\ChatRepository;
use App\Repositories\CoinRepository;
use App\Repositories\interfaces\IMerchantAppliesRepository;
use App\Repositories\interfaces\ICartRepository;
use App\Repositories\interfaces\IChatRepository;
use App\Repositories\interfaces\ICoinRepository;
use App\Repositories\interfaces\IProductCategoryRepository;
use App\Repositories\interfaces\IProductRepository;
use App\Repositories\interfaces\IUserReportRepository;
use App\Repositories\interfaces\IUserRepository;
use App\Repositories\MerchantAppliesRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserReportRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(IUserRepository::class, UserRepository::class);
        $this->app->bind(IProductRepository::class, ProductRepository::class);
        $this->app->bind(IProductCategoryRepository::class, ProductCategoryRepository::class);
        $this->app->bind(ICartRepository::class, CartRepository::class);
        $this->app->bind(IChatRepository::class, ChatRepository::class);
        $this->app->bind(ICoinRepository::class, CoinRepository::class);
        $this->app->bind(IUserReportRepository::class, UserReportRepository::class);
        $this->app->bind(IMerchantAppliesRepository::class, MerchantAppliesRepository::class);

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
