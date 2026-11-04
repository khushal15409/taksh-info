<?php

namespace App\Providers;

use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\QuickVendorRepositoryInterface;
use App\Repositories\Eloquent\QuickVendorRepository;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DeliveryCheckRepositoryInterface;
use App\Repositories\Contracts\ProductSearchRepositoryInterface;
use App\Repositories\Contracts\ReviewRepositoryInterface;
use App\Repositories\Contracts\RecentSearchRepositoryInterface;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\WishlistRepositoryInterface;
use App\Repositories\Contracts\CheckoutRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\StockRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ReturnRepositoryInterface;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Contracts\CourierBookingRepositoryInterface;
use App\Repositories\Contracts\CourierShipmentRepositoryInterface;
use App\Repositories\Contracts\ServiceBookingRepositoryInterface;
use App\Repositories\Contracts\SupportRequestRepositoryInterface;
use App\Repositories\Contracts\ServiceRefundRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Contracts\BankDetailRepositoryInterface;
use App\Repositories\Contracts\BannerRepositoryInterface;
use App\Repositories\Contracts\CartCatalogRepositoryInterface;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RecentViewRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\CatalogRepository;
use App\Repositories\Eloquent\CategoryRepository;
use App\Repositories\Eloquent\DeliveryCheckRepository;
use App\Repositories\Eloquent\ProductSearchRepository;
use App\Repositories\Eloquent\ReviewRepository;
use App\Repositories\Eloquent\RecentSearchRepository;
use App\Repositories\Eloquent\AddressRepository;
use App\Repositories\Eloquent\LocationRepository;
use App\Repositories\Eloquent\WishlistRepository;
use App\Repositories\Eloquent\CheckoutRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\StockRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\ReturnRepository;
use App\Repositories\Eloquent\NotificationRepository;
use App\Repositories\Eloquent\CourierBookingRepository;
use App\Repositories\Eloquent\CourierShipmentRepository;
use App\Repositories\Eloquent\ServiceBookingRepository;
use App\Repositories\Eloquent\SupportRequestRepository;
use App\Repositories\Eloquent\ServiceRefundRepository;
use App\Repositories\Eloquent\WalletRepository;
use App\Repositories\Eloquent\BankDetailRepository;
use App\Repositories\Eloquent\BannerRepository;
use App\Repositories\Eloquent\CartCatalogRepository;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Eloquent\CouponRepository;
use App\Repositories\Eloquent\DashboardRepository;
use App\Repositories\Eloquent\OtpRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\RecentViewRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\SaleRepository;
use App\Repositories\Eloquent\SettingRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Location\NominatimPlaceLookup;
use App\Services\Logistics\LogisticShipmentStatusSync;
use App\Services\Logistics\ShipmentStatusSync;
use App\Services\Orders\LogOrderNotifier;
use App\Services\Orders\OrderNotifier;
use App\Services\Payments\HttpRazorpayGateway;
use App\Services\Courier\CourierPhotoStore;
use App\Services\Courier\DiskCourierPhotoStore;
use App\Services\Payments\RazorpayGateway;
use App\Services\Shiprocket\HttpShiprocketApi;
use App\Services\Shiprocket\ShiprocketApi;
use App\Services\Location\PlaceLookup;
use App\Services\Returns\DiskReturnMediaStore;
use App\Services\Returns\ReturnMediaStore;
use App\Services\Sms\ApitxtSmsGateway;
use App\Services\Sms\SmsGateway;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        OtpRepositoryInterface::class => OtpRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        CartRepositoryInterface::class => CartRepository::class,
        CartCatalogRepositoryInterface::class => CartCatalogRepository::class,
        QuickVendorRepositoryInterface::class => QuickVendorRepository::class,
        CouponRepositoryInterface::class => CouponRepository::class,
        BannerRepositoryInterface::class => BannerRepository::class,
        BankDetailRepositoryInterface::class => BankDetailRepository::class,
        WalletRepositoryInterface::class => WalletRepository::class,
        NotificationRepositoryInterface::class => NotificationRepository::class,
        ServiceBookingRepositoryInterface::class => ServiceBookingRepository::class,
        ServiceRefundRepositoryInterface::class => ServiceRefundRepository::class,
        CourierBookingRepositoryInterface::class => CourierBookingRepository::class,
        SupportRequestRepositoryInterface::class => SupportRequestRepository::class,
        CourierShipmentRepositoryInterface::class => CourierShipmentRepository::class,
        ReturnRepositoryInterface::class => ReturnRepository::class,
        OrderRepositoryInterface::class => OrderRepository::class,
        StockRepositoryInterface::class => StockRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        CheckoutRepositoryInterface::class => CheckoutRepository::class,
        WishlistRepositoryInterface::class => WishlistRepository::class,
        LocationRepositoryInterface::class => LocationRepository::class,
        AddressRepositoryInterface::class => AddressRepository::class,
        CatalogRepositoryInterface::class => CatalogRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        DeliveryCheckRepositoryInterface::class => DeliveryCheckRepository::class,
        ProductSearchRepositoryInterface::class => ProductSearchRepository::class,
        ReviewRepositoryInterface::class => ReviewRepository::class,
        RecentSearchRepositoryInterface::class => RecentSearchRepository::class,
        DashboardRepositoryInterface::class => DashboardRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        SaleRepositoryInterface::class => SaleRepository::class,
        RecentViewRepositoryInterface::class => RecentViewRepository::class,
        SmsGateway::class => ApitxtSmsGateway::class,
        PlaceLookup::class => NominatimPlaceLookup::class,
        ReturnMediaStore::class => DiskReturnMediaStore::class,
        RazorpayGateway::class => HttpRazorpayGateway::class,
        ShiprocketApi::class => HttpShiprocketApi::class,
        CourierPhotoStore::class => DiskCourierPhotoStore::class,
        OrderNotifier::class => LogOrderNotifier::class,
        ShipmentStatusSync::class => LogisticShipmentStatusSync::class,
    ];

    /** One instance per request: the settings repository remembers what it already read. */
    public array $singletons = [
        SettingRepositoryInterface::class => SettingRepository::class,
        \App\Services\HeaderStateService::class => \App\Services\HeaderStateService::class,
    ];
}
