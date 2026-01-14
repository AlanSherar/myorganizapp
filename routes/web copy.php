<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PassportController;
use App\Http\Controllers\AcctivateController;
use App\Http\Controllers\Admin\BinController;
use App\Http\Controllers\Admin\LogsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\API\BinAPIController;
use App\Http\Controllers\ShipStationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BinTypeController;
use App\Http\Controllers\Admin\CarrierController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\PackagingSSCCController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ShippingController;
use App\Http\Controllers\Admin\UserTypeController;
use App\Http\Controllers\API\ProductAPIController;
use App\Http\Controllers\PackagingOrderController;
use App\Http\Controllers\Admin\ClockTimeController;
use App\Http\Controllers\Admin\PriceCardController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\API\LocationAPIController;
use App\Http\Controllers\API\WarehouseAPIController;
use App\Http\Controllers\Admin\ProductTypeController;
use App\Http\Controllers\Admin\LocationTypeController;
use App\Http\Controllers\API\ShipStationAPIController;
use App\Http\Controllers\Admin\PackagingTypeController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PackagingVendorController;
use App\Http\Controllers\Supervisor\SalesOrdersController;
use App\Http\Controllers\API\InventoryProductAPIController;
use App\Http\Controllers\Admin\PackagingReferenceController;
use App\Http\Controllers\API\TransactionDetailsAPIController;
use App\Http\Controllers\Supervisor\InventoryProductController;
use App\Http\Controllers\Supervisor\DashboardIndicatorController;
use App\Http\Controllers\Supervisor\TransactionProductController;
use App\Http\Controllers\Supervisor\SupervisorDashboardController;
use App\Http\Controllers\Fulfillment\SalesOrdersFulfillmentController;
use App\Http\Controllers\Fulfillment\PackagingOrderFulfillmentController;



// PUBLIC LOGIN
Route::get('/login', [AuthController::class, 'loginForm'])->name('loginForm');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/send-token-form', [AuthController::class, 'sendTokenForm'])->name('sendTokenForm');
Route::post('/send-token', [AuthController::class, 'sendToken'])->name('sendToken');
Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('resetPassword');
Route::post('/update-password', [AuthController::class, 'updatePassword'])->name('updatePassword');

// PUBLIC (with PIN) CLOCK TIME
Route::post('/clock-action-pin', [ClockTimeController::class, 'clockActionPin'])->name('clockActionPin');
Route::get('/check-clock-status/{secret_pin}', [ClockTimeController::class, 'checkClockStatus'])->name('checkClockStatus');
Route::get('/clock-time', [ClockTimeController::class, 'clockTime'])->name('clockTime');

Route::post('/set-locale', [PageController::class, 'setLanguage'])->name('setLanguage');
// CLIENTS
Route::get('/client-search', [ClientController::class, 'search'])->name('clientSearch');

// AUTHENTICATE
Route::middleware(['auth', 'user_type:OWNER', 'session_lifetime', 'lang'])->group(function () {
    Route::get('/', [PageController::class, 'index'])->name('index');
    Route::get('/ready-to-pack', [AcctivateController::class, 'getAcctivateOrders'])->name('getAcctivateOrders');
    Route::get('/orders-to-pick', [SalesOrdersController::class, 'getOrdersToPick'])->name('getOrdersToPick');
    Route::get('/ready-to-complete', [SalesOrdersController::class, 'getCrossDockingShipped'])->name('getCrossDockingShipped');
    Route::get('/packaging-sscc-list', [PackagingSSCCController::class, 'packagingSSCCList'])->name('packagingSSCCList');
    Route::get('/packaging-sscc-search', [PackagingSSCCController::class, 'packagingSSCCSearch'])->name('packagingSSCCSearch');

    // step to packaging 
    Route::get('/packaging-order-select', [PackagingOrderController::class, 'packagingOrderSelect'])->name('packagingOrderSelect');
    Route::post('/packaging-order-store', [PackagingOrderController::class, 'clientPackagingStore'])->name('clientPackagingStore');
    Route::get('/packaging-order-weight', [PackagingOrderController::class, 'packagingOrderWeight'])->name('packagingOrderWeight');
    Route::post('/packaging-weight-store', [PackagingOrderController::class, 'packagingWeightStore'])->name('packagingWeightStore');
    Route::post('/without-packaging-provider', [PackagingOrderController::class, 'storeWithoutPackagingProvider'])->name('storeWithoutPackagingProvider');
    Route::get('/packaging-multi-pack', [PackagingOrderController::class, 'packagingMultiPack'])->name('packagingMultiPack');
    Route::get('/order-check/{orderNumber}', [PackagingOrderController::class, 'checkOrder'])->name('order.check');
    Route::post('/cancel-packaging-orders', [PackagingOrderController::class, 'cancelOrders'])->name('cancelPackagingOrders');

    // step packed
    Route::get('/packed-order-list', [PackagingOrderController::class, 'packedOrderList'])->name('packedOrderList');
    Route::get('/packaging-order-detail/{orderNumber}', [PackagingOrderController::class, 'packagingOrderDetail'])->name('packagingOrderDetail');
    Route::get('/packaging-order-edit/{id}', [PackagingOrderController::class, 'packagingOrderEdit'])->name('packagingOrderEdit');
    Route::post('/packaging-order-update', [PackagingOrderController::class, 'packagingOrderUpdate'])->name('packagingOrderUpdate');
    Route::delete('/packaging-order-delete', [PackagingOrderController::class, 'packagingOrderDelete'])->name('packagingOrderDelete');

    // step shipped
    Route::get('/shipped-order-list', [PackagingOrderController::class, 'shippedOrderList'])->name('shippedOrderList');
    Route::get('/packed-order-details/{order_number}', [PackagingOrderController::class, 'packedOrderDetails'])->name('packedOrderDetails');
    Route::get('/order-on-shipstation/{order_number}', [PackagingOrderController::class, 'getOrderOnShipstation'])->name('getOrderOnShipstation');
    Route::get('/shipped-order-report', [PackagingOrderController::class, 'downloadShippedOrderReport'])->name('downloadShippedOrderReport');

    Route::get('/voided-orders-list', [PackagingOrderController::class, 'voidedOrderList'])->name('voidedOrderList');

    Route::get('/packaging-order-report', [PackagingOrderController::class, 'downloadPackagingReport'])->name('downloadPackagingReport');
    Route::get('/print-order-detail/{orderNumber}', [PackagingOrderController::class, 'printOrderDetail'])->name('printOrderDetail');

    Route::post('/export-report', [ReportController::class, 'exportReport'])->name('exportReport');

    // SHIPSTATION SERVICE
    Route::get('/create-label/{orderNumber}', [ShipStationController::class, 'showCreateLabel'])->name('showCreateLabel');
    Route::get('/estimate-label', [ShipStationController::class, 'getRates'])->name('getRates');
    Route::post('/purchase-label', [ShipStationController::class, 'getLabelMultiPackage'])->name('getLabelMultiPackage');
    Route::post('/create-label-order', [ShipStationController::class, 'getLabelSinglePackage'])->name('getLabelSinglePackage');
    Route::delete('/void-label', [ShipStationController::class, 'voidLabel'])->name('voidLabel');
    Route::get('/ready-to-ship', [ShipStationController::class, 'readyToShip'])->name('readyToShip');

    // clock time
    Route::get('/clock-in-time', [ClockTimeController::class, 'clockInTime'])->name('clockInTime');
    Route::get('/clock-out-time', [ClockTimeController::class, 'clockOutTime'])->name('clockOutTime');
    Route::get('/break-in-time', [ClockTimeController::class, 'breakInTime'])->name('breakInTime');
    Route::get('/break-out-time', [ClockTimeController::class, 'breakOutTime'])->name('breakOutTime');

    // api test shipstation
    Route::get('/order/{orderId}', [ShipStationAPIController::class, 'getOrder'])->name('getOrder');
    Route::get('/order-update-size/{orderNumber}', [ShipStationAPIController::class, 'updateOrderSize'])->name('updateOrderSize');
    Route::get('/shipment-order-number/{orderNumber}', [ShipStationAPIController::class, 'getShipmentOrderNumber'])->name('getShipmentOrderNumber');
    Route::get('/rates-by-shipment-id/{shipmentID}', [ShipStationAPIController::class, 'getRatesByShipmentId'])->name('getRatesByShipmentId');
    Route::get('/warehouses-list', [ShipStationAPIController::class, 'getWarehousesList'])->name('getWarehousesList');
    Route::get('/carriers-list', [ShipStationAPIController::class, 'getCarriersList'])->name('getCarriersList');
    Route::get('/carrier/{carrier_code}', [ShipStationAPIController::class, 'getCarrier'])->name('getCarrier');
    Route::post('/cancel-order', [PackagingOrderController::class, 'cancelOrder'])->name('cancelOrder');

    Route::get('/passport-create-ship/{orderNumber}', [PassportController::class, 'passportCreateShip'])->name('passportCreateShip');
    Route::post('/passport-rates/{orderNumber}', [PassportController::class, 'passportGetRates'])->name('passportGetRates');
    Route::post('/passport-purchase-label/{orderNumber}', [PassportController::class, 'passportPurchaseLabel'])->name('passportPurchaseLabel');
    Route::get('/passport-label/{orderNumber}', [PassportController::class, 'passportGetLabel'])->name('passportGetLabel');
    Route::post('/passport-void-label', [PassportController::class, 'passportVoidLabel'])->name('passportVoidLabel');
    Route::post('/passport-print-label', [PassportController::class, 'passportPrintLabel'])->name('passportPrintLabel');
    Route::get('/passport-report-orders', [PassportController::class, 'passportReportOrders'])->name('passportReportOrders');
    Route::get('/passport-report-orders-csv', [PassportController::class, 'passportReportOrdersCSV'])->name('passportReportOrdersCSV');
    
    // Shipping credit
    Route::get('/shipping-credit', [PackagingOrderFulfillmentController::class, 'shippingCredit'])->name('shippingCredit');

    // I took these from api.php since whole file is not working, tried debuging and fixing it but issue persists. 
    // When api.php is correctly configured and working, these can be moved again
    // Remember removing /api prefix depending on RouteServiceProvider.

    Route::get('/api/products/search', [ProductAPIController::class, 'getBySearch'])->name('getProductBySearch');
    Route::get('/api/products/name/{name}', [ProductAPIController::class, 'getByName'])->name('getProductsByName');
    Route::get('/api/products/type/{type}', [ProductAPIController::class, 'getByType'])->name('getProductsByType');
    Route::get('/api/products/{id}', [ProductAPIController::class, 'getByID'])->name('getProductByID');
    Route::get('/api/products/{id}/components', [ProductAPIController::class, 'getAllComponentsByKitID'])->name('getComponents');
    Route::get('/api/products/{id}/barcodes', [ProductAPIController::class, 'getBarcodesByID'])->name('getBarcodes');
    Route::get('/api/products', [ProductAPIController::class, 'getAll'])->name('getAllProducts');

    // WAREHOUSES API
    Route::get('/api/warehouses/site/{site_id}', [WarehouseAPIController::class, 'getBySiteId'])->name('getWarehousesBySiteId');

    // LOCATIONS API
    // Route::get('/api/locations/warehouse/{warehouse_id}', [LocationAPIController::class, 'getByWarehouseId'])->name('getLocationsByWarehouseId');
    Route::get('/api/locations/site/{site_id}', [LocationAPIController::class, 'getBySiteId'])->name('getLocationsBySiteId');
    Route::get('/api/locations', [LocationAPIController::class, 'getAll'])->name('getAllLocations');
    Route::get('/api/locations-site/{site_id}', [LocationAPIController::class, 'locationsBySite'])->name('locationsBySite');

    // BINS API
    Route::get('/api/bins', [BinAPIController::class, 'getAll'])->name('getAllBins');
    // Route::get('/api/bins/warehouse/{warehouse_id}', [BinAPIController::class, 'getByWarehouseId'])->name('getBinsByWarehouseId');
    Route::get('/api/bins/location/{location_id}', [BinAPIController::class, 'getByLocationId'])->name('getBinsByLocationId');

    // INVENTORY PRODUCTS API
    Route::get('/api/inventory-products', [InventoryProductAPIController::class, 'getAll'])->name('getAllInventoryProducts');
    Route::get('/api/inventory-products/product', [InventoryProductAPIController::class, 'getInventoryByProduct'])->name('getInventoryByProduct');
    Route::get('/api/inventory-products/lot-numbers', [InventoryProductAPIController::class, 'getLotNumbers'])->name('getLotNumbers');
    Route::get('/api/inventory-products/serial-numbers', [InventoryProductAPIController::class, 'getSerialNumbers'])->name('getSerialNumbers');
});

// SUPERVISOR
Route::middleware(['auth', 'user_type:OWNER', 'user_role:admin,supervisor', 'session_lifetime', 'lang'])->group(function () {
    // INDICATORS DASHBOARD
    Route::get('/dashboard', [SupervisorDashboardController::class, 'dashboardIndicator'])->name('dashboardIndicator');

    Route::get('/orders-on-hold/count', [SupervisorDashboardController::class, 'ordersOnHoldCount'])->name('ordersOnHoldCount');
    Route::get('/ready-to-pick/count', [SupervisorDashboardController::class, 'readyToPickCount'])->name('readyToPickCount');
    Route::get('/orders-unshipped/count', [SupervisorDashboardController::class, 'ordersUnshippedCount'])->name('ordersUnshippedCount');
    Route::get('/orders-shipped/count/{filerTime}', [SupervisorDashboardController::class, 'ordersShippedCount'])->name('ordersShippedCount');
    Route::get('/time-worked/count/{filerTime}', [SupervisorDashboardController::class, 'timeWorkedCount'])->name('timeWorkedCount');
    Route::get('/time-to-ship/count/{filerTime}/{freight}', [SupervisorDashboardController::class, 'timeToShipCount'])->name('timeToShipCount');

    Route::get('/orders-on-hold', [DashboardIndicatorController::class, 'ordersOnHold'])->name('ordersOnHold');
    Route::get('/ready-to-pick', [DashboardIndicatorController::class, 'readyToPick'])->name('readyToPick');
    Route::get('/order-unshipped', [DashboardIndicatorController::class, 'orderUnshipped'])->name('orderUnshipped');
    Route::get('/orders-shipped', [DashboardIndicatorController::class, 'orderShipped'])->name('orderShipped');
    Route::get('/time-worked', [DashboardIndicatorController::class, 'timeWorked'])->name('timeWorked');
    Route::get('/time-to-ship', [DashboardIndicatorController::class, 'timeToShip'])->name('timeToShip');

    // clock time management
    Route::get('/clock-time-employees', [ClockTimeController::class, 'clockTimeEmployees'])->name('clockTimeEmployees');
    Route::get('/clock-time-details/{user_id}', [ClockTimeController::class, 'clockTimeDetails'])->name('clockTimeDetails');
    Route::get('/clock-time-details-edit/{id}', [ClockTimeController::class, 'clockTimeEdit'])->name('clockTimeEdit');
    Route::post('/clock-time-update', [ClockTimeController::class, 'clockTimeUpdate'])->name('clockTimeUpdate');
    Route::delete('/clock-time-details-delete', [ClockTimeController::class, 'clockTimeDelete'])->name('clockTimeDelete');
    Route::get('/clock-time-report', [ClockTimeController::class, 'clockTimeReport'])->name('clockTimeReport');
    Route::get('/break-times', [ClockTimeController::class, 'getBreakTimes'])->name('breakTimeDetails');

    //transactions
    Route::get('/transfer-products', [TransactionController::class, 'transferProducts'])->name('transferProducts');
    Route::get('/location-products', [TransactionController::class, 'locationProducts'])->name('locationProducts');
    Route::get('/locations-warehouse/{guid_wh}', [TransactionController::class, 'getLocationsWarehouse'])->name('getLocationsWarehouse');
    Route::post('/csv-transaction', [TransactionController::class, 'handleCsvTransaction'])->name('handleCsvTransaction');
    Route::get('/transactions-list', [TransactionController::class, 'getTransactionsList'])->name('getTransactionsList');
    Route::get('/transaction-details/{id}', [TransactionController::class, 'getTransactionDetails'])->name('getTransactionDetails');
    Route::post('/csv-products-location', [TransactionController::class, 'handleCsvProductsLocation'])->name('handleCsvProductsLocation');

    Route::get('/transaction-product-list', [TransactionProductController::class, 'transactionProductList'])->name('transactionProductList');
    Route::get('/transaction-product-search', [TransactionProductController::class, 'transactionProductSearch'])->name('transactionProductSearch');
    Route::get('/transaction-product-create', [TransactionProductController::class, 'transactionProductCreate'])->name('transactionProductCreate');
    Route::get('/transaction-product-confirm', [TransactionProductController::class, 'transactionProductConfirm'])->name('transactionProductConfirm');
    Route::post('/transaction-product-store', [TransactionProductController::class, 'transactionProductStore'])->name('transactionProductStore');
    Route::get('/transaction-product-edit/{id}', [TransactionProductController::class, 'transactionProductEdit'])->name('transactionProductEdit');
    Route::post('/transaction-product-update/{id}', [TransactionProductController::class, 'transactionProductUpdate'])->name('transactionProductUpdate');
    Route::get('/transaction-product-detail/{id}', [TransactionProductController::class, 'transactionProductDetail'])->name('transactionProductDetail');
    Route::get('/transaction-product-post/{id}', [TransactionProductController::class, 'getTransactionProductPost'])->name('getTransactionProductPost');
    Route::post('/transaction-product-post/{id}', [TransactionProductController::class, 'transactionProductPost'])->name('transactionProductPost');
    Route::get('/transaction-product-unpost/{id}', [TransactionProductController::class, 'getTransactionProductUnpost'])->name('getTransactionProductUnpost');
    Route::post('/transaction-product-unpost/{id}', [TransactionProductController::class, 'transactionProductUnpost'])->name('transactionProductUnpost');
    
    // Endpoint for get all transaction details by transaction id, can paginate
    Route::get('/api/transaction-details/transaction/{transactionId}', [TransactionDetailsAPIController::class, 'getAllByTransaction']);

    Route::get('/movements-list', [TransactionProductController::class, 'movementList'])->name('movementList');
    Route::get('/getLocationByBin/{binId}', [LocationController::class, 'getLocationByBin'])->name('getLocationByBin');

    Route::get('/inventory-product-list', [InventoryProductController::class, 'inventoryProductList'])->name('inventoryProductList');
    Route::get('/inventory-product-confirm/{id}', [InventoryProductController::class, 'inventoryProductConfirm'])->name('inventoryProductConfirm');
    Route::get('/inventory-product-edit/{id}', [InventoryProductController::class, 'inventoryProductEdit'])->name('inventoryProductEdit');
    Route::post('/inventory-product-update/{id}', [InventoryProductController::class, 'inventoryProductUpdate'])->name('inventoryProductUpdate');
    Route::get('/inventory-product-export', [InventoryProductController::class, 'exportInventory'])->name('exportInventory');

    // SHIPPING QUOTE 
    Route::get('/shipping-quote', [ShippingController::class, 'shippingQuote'])->name('shippingQuote');
    Route::get('/shipping-quote-detail/{group_id}', [ShippingController::class, 'shippingQuoteDetails'])->name('shippingQuoteDetails');
    Route::get('/shipping-quote-export/{group_id}', [ShippingController::class, 'shippingQuoteExport'])->name('shippingQuoteExport');
    Route::get('/shipping-quote-delete/{group_id}', [ShippingController::class, 'shippingQuoteDelete'])->name('shippingQuoteDelete');

    Route::get('/price-card', [PriceCardController::class, 'priceCard'])->name('priceCard');
    Route::get('/price-card-detail/{group_id}', [PriceCardController::class, 'priceCardDetails'])->name('priceCardDetails');
    Route::get('/price-card-export/{group_id}', [PriceCardController::class, 'priceCardExport'])->name('priceCardExport');
    Route::get('/price-card-delete/{group_id}', [PriceCardController::class, 'priceCardDelete'])->name('priceCardDelete');

    //PRODUCT MANAGEMENT
    Route::get('/product-create', [ProductController::class, 'create'])->name('productCreate');
    Route::post('/product-store', [ProductController::class, 'store'])->name('productStore');
    Route::get('/products-list', [ProductController::class, 'list'])->name('productsList');
    Route::get('/product-edit/{id}', [ProductController::class, 'edit'])->name('productEdit');
    Route::post('/product-update/{id}', [ProductController::class, 'update'])->name('productUpdate');
    Route::post('/sales-orders-cancel/{id}', [SalesOrdersController::class, 'saleOrderCancel'])->name('saleOrderCancel');
    Route::post('/product-status-toggle', [ProductController::class, 'statusToggle'])->name('productStatusToggle');
    Route::post('/products-upload-csv', [ProductController::class, 'uploadCSV'])->name('productsUploadCSV');
    Route::get('/download-empty-csv', [ProductController::class, 'downloadEmptyCSV'])->name('downloadEmptyCSV');
    Route::post('/products-print', [ProductController::class, 'print'])->name('productsPrint');
    Route::get('/products-company/{company_id}', [ProductController::class, 'getByCompany'])->name('getByCompany');

    //SALES ORDERS MANAGEMENT
    Route::get('/sales-orders-list', [SalesOrdersController::class, 'saleOrdersList'])->name('saleOrdersList');
    Route::get('/sales-orders-import-errors/{key}', [SalesOrdersController::class, 'downloadImportErrors'])->name('saleOrdersImportErrors');
    Route::get('/sales-orders-create', [SalesOrdersController::class, 'saleOrderCreate'])->name('saleOrderCreate');
    Route::post('/sales-orders-store', [SalesOrdersController::class, 'saleOrderStore'])->name('saleOrderStore');
    Route::post('/sales-orders-import', [SalesOrdersController::class, 'saleOrderImport'])->name('saleOrderImport');
    Route::get('/sales-orders-edit/{id}', [SalesOrdersController::class, 'saleOrderEdit'])->name('saleOrderEdit');
    Route::post('/sales-orders-update/{id}', [SalesOrdersController::class, 'saleOrderUpdate'])->name('saleOrderUpdate');
    Route::get('/sales-orders-detail/{id}', [SalesOrdersController::class, 'saleOrderDetails'])->name('saleOrderDetails');
    Route::get('/sales-orders-template-csv', [SalesOrdersController::class, 'saleOrderTemplate'])->name('saleOrderTemplate');
    Route::get('/sale-orders-import-errors/{key}', [SalesOrdersController::class, 'downloadImportErrors'])->name('saleOrdersImportErrors');
    Route::get('/sale-order-check/{orderNumber}', [SalesOrdersController::class, 'checkOrder'])->name('saleOrder.check');
    Route::post('/complete-sale-orders', [SalesOrdersController::class, 'completeSaleOrders'])->name('completeSaleOrders');
    Route::post('/select-orders-to-pack', [SalesOrdersFulfillmentController::class, 'selectOrdersToPack'])->name('selectOrdersToPack');

});

// --- ADMIN ---
Route::prefix('admin')->middleware(['auth', 'user_type:OWNER', 'user_role:admin', 'session_lifetime', 'lang'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'dashboard'])->name('dashboard');

    Route::get('/settings', [SettingController::class, 'settingsView'])->name('settingsView');
    Route::post('/create-setting', [SettingController::class, 'createSetting'])->name('createSetting');
    Route::delete('/delete-setting', [SettingController::class, 'settingDelete'])->name('settingDelete');
    Route::post('/setting-update', [SettingController::class, 'settingUpdate'])->name('settingUpdate');

    Route::get('/shipped-billing', [ReportController::class, 'shippedBilling'])->name('shippedBilling');
    Route::post('/shipped-billing-report', [ReportController::class, 'shippedBillingReport'])->name('shippedBillingReport');
    Route::post('/send-email-report', [ReportController::class, 'sendEmailReport'])->name('sendEmailReport');

    //USER MANAGEMENT
    Route::get('/user-create', [UserController::class, 'userCreate'])->name('userCreate');
    Route::post('/user-store', [UserController::class, 'userStore'])->name('userStore');
    Route::get('/users-list', [UserController::class, 'usersList'])->name('usersList');
    Route::get('/users', [UserController::class, 'getUsers'])->name('getUsers');
    Route::get('/user-edit/{id}', [UserController::class, 'userEdit'])->name('userEdit');
    Route::get('/user-details/{id}', [UserController::class, 'userDetails'])->name('userDetails');
    Route::post('/user-update/{id}', [UserController::class, 'userUpdate'])->name('userUpdate');
    Route::post('/user-delete', [UserController::class, 'userDelete'])->name('userDelete');

    //LOG MANAGEMENT
    Route::get('/logs-actions-list', [LogsController::class, 'logsActionsList'])->name('logsActionsList');
    Route::get('/logs-connection-list', [LogsController::class, 'logsConnectionsList'])->name('logsConnectionsList');
    Route::get('/logs-actions-report', [LogsController::class, 'downloadActionsLogsReport'])->name('downloadActionsLogsReport');
    Route::get('/logs-connections-report', [LogsController::class, 'downloadConnectionsLogsReport'])->name('downloadConnectionsLogsReport');

    //PACKAGING PROVIDER MANAGEMENT
    Route::get('/packaging-provider-list', [PackagingVendorController::class, 'packagingProviderList'])->name('packagingProviderList');
    Route::get('/packaging-provider-create', [PackagingVendorController::class, 'packagingProviderCreate'])->name('packagingProviderCreate');
    Route::post('/packaging-provider-store', [PackagingVendorController::class, 'packagingProviderStore'])->name('packagingProviderStore');
    Route::get('/packaging-provider-edit', [PackagingVendorController::class, 'packagingProviderEdit'])->name('packagingProviderEdit');
    Route::post('/packaging-provider-update', [PackagingVendorController::class, 'packagingProviderUpdate'])->name('packagingProviderUpdate');
    Route::delete('/packaging-provider-delete', [PackagingVendorController::class, 'packagingProviderDelete'])->name('packagingProviderDelete');

    //PACKAGING TYPE MANAGEMENT
    Route::get('/packaging-type-list', [PackagingTypeController::class, 'packagingTypeList'])->name('packagingTypeList');
    Route::get('/packaging-type-create', [PackagingTypeController::class, 'packagingTypeCreate'])->name('packagingTypeCreate');
    Route::post('/packaging-type-store', [PackagingTypeController::class, 'packagingTypeStore'])->name('packagingTypeStore');
    Route::get('/packaging-type-edit', [PackagingTypeController::class, 'packagingTypeEdit'])->name('packagingTypeEdit');
    Route::post('/packaging-type-update/{id}', [PackagingTypeController::class, 'packagingTypeUpdate'])->name('packagingTypeUpdate');
    Route::delete('/packaging-type-delete', [PackagingTypeController::class, 'packagingTypeDelete'])->name('packagingTypeDelete');

    //PACKAGING REFERENCE MANAGEMENT
    Route::get('/packaging-reference-list', [PackagingReferenceController::class, 'packagingReferenceList'])->name('packagingReferenceList');
    Route::get('/packaging-reference-create', [PackagingReferenceController::class, 'packagingReferenceCreate'])->name('packagingReferenceCreate');
    Route::post('/packaging-reference-store', [PackagingReferenceController::class, 'packagingReferenceStore'])->name('packagingReferenceStore');
    Route::get('/packaging-reference-edit', [PackagingReferenceController::class, 'packagingReferenceEdit'])->name('packagingReferenceEdit');
    Route::post('/packaging-reference-update/{id}', [PackagingReferenceController::class, 'packagingReferenceUpdate'])->name('packagingReferenceUpdate');
    Route::delete('/packaging-reference-delete', [PackagingReferenceController::class, 'packagingReferenceDelete'])->name('packagingReferenceDelete');

    //SITE MANAGEMENT
    Route::get('/site-create', [SiteController::class, 'siteCreate'])->name('siteCreate');
    Route::post('/site-store', [SiteController::class, 'siteStore'])->name('siteStore');
    Route::get('/sites-list', [SiteController::class, 'sitesList'])->name('sitesList');
    Route::get('/sites', [SiteController::class, 'getSites'])->name('getSites');
    Route::get('/site-detail/{id}', [SiteController::class, 'siteDetails'])->name('siteDetails');
    Route::get('/site-edit/{id}', [SiteController::class, 'siteEdit'])->name('siteEdit');
    Route::post('/site-update/{id}', [SiteController::class, 'siteUpdate'])->name('siteUpdate');
    Route::post('/site-status-toggle/{id}', [SiteController::class, 'siteStatusToggle'])->name('siteStatusToggle');

    //LOCATION MANAGEMENT
    Route::get('/location-create', [LocationController::class, 'locationCreate'])->name('locationCreate');
    Route::post('/location-store', [LocationController::class, 'locationStore'])->name('locationStore');
    Route::get('/locations-list', [LocationController::class, 'locationsList'])->name('locationsList');
    Route::get('/location-edit/{id}', [LocationController::class, 'locationEdit'])->name('locationEdit');
    Route::get('/location-details/{id}', [LocationController::class, 'locationDetails'])->name('locationDetails');
    Route::post('/location-update/{id}', [LocationController::class, 'locationUpdate'])->name('locationUpdate');
    Route::post('/location-status-toggle', [LocationController::class, 'locationStatusToggle'])->name('locationStatusToggle');
    Route::post('/locations-upload-csv', [LocationController::class, 'locationsUploadCSV'])->name('locationsUploadCSV');

    //LOCATION TYPE MANAGEMENT
    Route::get('/location-type-create', [LocationTypeController::class, 'locationTypeCreate'])->name('locationTypeCreate');
    Route::post('/location-type-store', [LocationTypeController::class, 'locationTypeStore'])->name('locationTypeStore');
    Route::get('/location-types-list', [LocationTypeController::class, 'locationTypesList'])->name('locationTypesList');

    Route::get('/location-type-edit/{id}', [LocationTypeController::class, 'locationTypeEdit'])->name('locationTypeEdit');
    Route::post('/location-type-update/{id}', [LocationTypeController::class, 'locationTypeUpdate'])->name('locationTypeUpdate');
    Route::post('/location-type-status-toggle', [LocationTypeController::class, 'locationTypeStatusToggle'])->name('locationTypeStatusToggle');

    // COMPANIES MANAGEMENT
    Route::get('/companies-list', [CompanyController::class, 'companiesList'])->name('companiesList');
    Route::get('/company-detail/{id}', [CompanyController::class, 'companyDetail'])->name('companyDetail');
    Route::get('/company-create', [CompanyController::class, 'companyCreate'])->name('companyCreate');
    Route::post('/company-store', [CompanyController::class, 'companyStore'])->name('companyStore');
    Route::get('/company-edit/{id}', [CompanyController::class, 'companyEdit'])->name('companyEdit');
    Route::post('/company-update/{id}', [CompanyController::class, 'companyUpdate'])->name('companyUpdate');
    Route::get('/company-carriers', [CompanyController::class, 'companyCarriers'])->name('companyCarriers');
    Route::delete('/company-warehouse-delete/{company}/{warehouse}', [CompanyController::class, 'deleteCompanyWarehouse'])->name('deleteCompanyWarehouse');
    Route::delete('/company-delete', [CompanyController::class, 'companyDelete'])->name('companyDelete');
    Route::patch('/companies/{company}/credit', [CompanyController::class, 'updateCredit'])->name('companies.update-credit');
    Route::get('/companies-shippings', [CompanyController::class, 'companiesShippings'])->name('companiesShippings');
    Route::get('/company-shippings/{id}', [CompanyController::class, 'companyShippings'])->name('companyShippings');

    Route::get('/packaging-report', [PackagingOrderController::class, 'packagingReport'])->name('packagingReport');
    Route::get('/billing-packaging-report', [PackagingOrderController::class, 'billingPackagingReport'])->name('billingPackagingReport');

    // WAREHOUSES 
    Route::get('/warehouses-list', [WarehouseController::class, 'warehousesList'])->name('warehousesList');
    Route::get('/warehouse-create', [WarehouseController::class, 'warehouseCreate'])->name('warehouseCreate');
    Route::post('/warehouse-store', [WarehouseController::class, 'warehouseStore'])->name('warehouseStore');
    Route::get('/warehouse-detail/{id}', [WarehouseController::class, 'warehouseDetails'])->name('warehouseDetails');
    Route::get('/warehouse-edit', [WarehouseController::class, 'warehouseEdit'])->name('warehouseEdit');
    Route::post('/warehouse-update/{id}', [WarehouseController::class, 'warehouseUpdate'])->name('warehouseUpdate');
    Route::post('/warehouse-activate', [WarehouseController::class, 'warehouseActivate'])->name('warehouseActivate');

    Route::get('/carriers-list', [CarrierController::class, 'carriersList'])->name('carriersList');
    Route::get('/carrier-detail/{id}', [CarrierController::class, 'carrierDetail'])->name('carrierDetail');
    Route::get('/carrier-create', [CarrierController::class, 'carrierCreate'])->name('carrierCreate');
    Route::post('/carrier-store', [CarrierController::class, 'carrierStore'])->name('carrierStore');
    Route::get('/carrier-edit', [CarrierController::class, 'carrierEdit'])->name('carrierEdit');
    Route::post('/carrier-update/{id}', [CarrierController::class, 'carrierUpdate'])->name('carrierUpdate');
    Route::delete('/carrier-delete', [CarrierController::class, 'carrierDelete'])->name('carrierDelete');

    //PRODUCT TYPE MANAGEMENT
    Route::get('/product-type-create', [ProductTypeController::class, 'create'])->name('productTypeCreate');
    Route::post('/product-type-store', [ProductTypeController::class, 'store'])->name('productTypeStore');
    Route::get('/product-types-list', [ProductTypeController::class, 'list'])->name('productTypesList');
    Route::get('/product-type-edit/{id}', [ProductTypeController::class, 'edit'])->name('productTypeEdit');
    Route::post('/product-type-update/{id}', [ProductTypeController::class, 'update'])->name('productTypeUpdate');
    Route::post('/product-type-status-toggle', [ProductTypeController::class, 'statusToggle'])->name('productTypeStatusToggle');

    Route::delete('/carrier-delete', [CarrierController::class, 'carrierDelete'])->name('carrierDelete');

    //BINS
    Route::get('/bins-list', [BinController::class, 'binsList'])->name('binsList');
    Route::get('/bin-create', [BinController::class, 'binCreate'])->name('binCreate');
    Route::post('/bin-store', [BinController::class, 'binStore'])->name('binStore');
    Route::get('/bin-edit/{id}', [BinController::class, 'binEdit'])->name('binEdit');
    Route::get('/bin-details/{id}', [BinController::class, 'binDetails'])->name('binDetails');
    Route::post('/bin-update/{id}', [BinController::class, 'binUpdate'])->name('binUpdate');
    Route::post('/bin-status-toggle', [BinController::class, 'binStatusToggle'])->name('binStatusToggle');
    Route::get('/bins-print/{id}', [BinController::class, 'binsPrint'])->name('binsPrint');
    Route::post('/bins-status/update', [BinController::class, 'binsSetStatus'])->name('binsSetStatus');

    Route::get('/bins-types-list', [BinTypeController::class, 'binsTypesList'])->name('binsTypesList');
    Route::get('/bin-type-create', [BinTypeController::class, 'binTypeCreate'])->name('binTypeCreate');
    Route::post('/bin-type-store', [BinTypeController::class, 'binTypeStore'])->name('binTypeStore');
    Route::get('/bin-type-edit/{id}', [BinTypeController::class, 'binTypeEdit'])->name('binTypeEdit');
    Route::post('/bin-type-update/{id}', [BinTypeController::class, 'binTypeUpdate'])->name('binTypeUpdate');
    Route::post('/bin-type-status-toggle', [BinTypeController::class, 'binTypeStatusToggle'])->name('binTypeStatusToggle');

    //BINS
    Route::get('/clients-list', [ClientController::class, 'list'])->name('clientsList');
    Route::get('/client-create', [ClientController::class, 'create'])->name('clientCreate');
    Route::post('/client-store', [ClientController::class, 'store'])->name('clientStore');
    Route::get('/client-edit/{id}', [ClientController::class, 'edit'])->name('clientEdit');
    Route::post('/client-update/{id}', [ClientController::class, 'update'])->name('clientUpdate');
    Route::get('/client-details/{id}', [ClientController::class, 'details'])->name('clientDetails');
    Route::post('/client-store', [ClientController::class, 'store'])->name('clientStore');
    Route::get('/clients-import-template-csv', [ClientController::class, 'clientsImportTemplate'])->name('clientsImportTemplate');
    Route::post('/clients-import-csv', [ClientController::class, 'import'])->name('clientImportCSV');

    //ROLE
    Route::get('/role-create', [RoleController::class, 'roleCreate'])->name('roleCreate');
    Route::post('/role-store', [RoleController::class, 'roleStore'])->name('roleStore');
    Route::get('/roles-list', [RoleController::class, 'rolesList'])->name('rolesList');
    Route::get('/roles', [RoleController::class, 'getRoles'])->name('getRoles');
    Route::get('/role-details/{id}', [RoleController::class, 'roleDetails'])->name('roleDetails');
    Route::get('/role-edit/{id}', [RoleController::class, 'roleEdit'])->name('roleEdit');
    Route::post('/role-update/{id}', [RoleController::class, 'roleUpdate'])->name('roleUpdate');

    //USER TYPES
    Route::get('/user-type-create', [UserTypeController::class, 'userTypeCreate'])->name('userTypeCreate');
    Route::post('/user-type-store', [UserTypeController::class, 'userTypeStore'])->name('userTypeStore');
    Route::get('/user-types-list', [UserTypeController::class, 'userTypesList'])->name('userTypesList');
    Route::get('/user-types', [UserTypeController::class, 'getUserTypes'])->name('getUserTypes');
    Route::get('/user-type-edit/{id}', [UserTypeController::class, 'userTypeEdit'])->name('userTypeEdit');
    Route::get('/user-type-details/{id}', [UserTypeController::class, 'userTypeDetails'])->name('userTypeDetails');
    Route::post('/user-type-update/{id}', [UserTypeController::class, 'userTypeUpdate'])->name('userTypeUpdate');

    //API
    Route::get('/shipment-shipment-id/{shipment_id}', [ShipStationAPIController::class, 'getShipmentById'])->name('getShipmentById');
    Route::get('/label/{label_id}', [ShipStationAPIController::class, 'getLabelById'])->name('getLabelById');
});

Route::middleware(['auth', 'user_type:OWNER,CLIENT', 'session_lifetime', 'lang'])->group(function () {

    //SALES ORDERS MANAGEMENT
    Route::get('/sales-orders-list', [SalesOrdersController::class, 'saleOrdersList'])->name('saleOrdersList');
    Route::get('/sales-orders-create', [SalesOrdersController::class, 'saleOrderCreate'])->name('saleOrderCreate');
    Route::post('/sales-orders-store', [SalesOrdersController::class, 'saleOrderStore'])->name('saleOrderStore');
    Route::post('/sales-orders-import', [SalesOrdersController::class, 'saleOrderImport'])->name('saleOrderImport');
    Route::get('/sales-orders-edit/{id}', [SalesOrdersController::class, 'saleOrderEdit'])->name('saleOrderEdit');
    Route::post('/sales-orders-cancel/{id}', [SalesOrdersController::class, 'saleOrderCancel'])->name('saleOrderCancel');
    Route::post('/sales-orders-update/{id}', [SalesOrdersController::class, 'saleOrderUpdate'])->name('saleOrderUpdate');
    Route::get('/sales-orders-detail/{id}', [SalesOrdersController::class, 'saleOrderDetails'])->name('saleOrderDetails');
    Route::get('/sales-orders-template-csv', [SalesOrdersController::class, 'saleOrderTemplate'])->name('saleOrderTemplate');

    // ORDERS TO PACK
    Route::get('/orders-to-pack-list', [SalesOrdersFulfillmentController::class, 'ordersToPackList'])->name('ordersToPackList');
    Route::post('/select-orders-to-pack', [SalesOrdersFulfillmentController::class, 'selectOrdersToPack'])->name('selectOrdersToPack');
    Route::post('/store-orders-to-pack', [SalesOrdersFulfillmentController::class, 'storeSaleOrders'])->name('storeSaleOrders');
    Route::post('/orders/unpack/{orderNumber}', [SalesOrdersFulfillmentController::class, 'unpackOrder'])->name('unpackOrder');
    
    // ORDERS TO SHIP
    Route::get('/orders-to-ship-list', [SalesOrdersFulfillmentController::class, 'ordersToShipList'])->name('ordersToShipList');
    
    //ORDERS SHIPPED
    Route::get('/orders-shipped-list', [SalesOrdersFulfillmentController::class, 'ordersShippedList'])->name('ordersShippedList');
    Route::get('/completed-orders-list', [SalesOrdersFulfillmentController::class, 'ordersCompletedList'])->name('ordersCompletedList');
    Route::get('/order-shipped-label/{order_number}', [SalesOrdersFulfillmentController::class, 'orderShippedLabel'])->name('orderShippedLabel');
    Route::get('/print-packaging-label/{order_number}', [SalesOrdersFulfillmentController::class, 'printPackagingLabel'])->name('printPackagingLabel');

    // SHIPSTATION API
    Route::get('/order-on-shipstation/{order_number}', [PackagingOrderController::class, 'getOrderOnShipstation'])->name('getOrderOnShipstation');
    Route::get('/order-to-ship/{orderNumber}', [SalesOrdersFulfillmentController::class, 'orderToShip'])->name('orderToShip');
    Route::get('/estimate-label', [ShipStationController::class, 'getRates'])->name('getRates');
    Route::post('/purchase-label', [ShipStationController::class, 'getLabelMultiPackage'])->name('getLabelMultiPackage');
    Route::post('/create-label-order', [ShipStationController::class, 'getLabelSinglePackage'])->name('getLabelSinglePackage');
    Route::delete('/void-label', [ShipStationController::class, 'voidLabel'])->name('voidLabel');

    Route::get('/test-view/{order_number}', [ShipStationController::class, 'testView'])->name('testView');

    // PRODUCTS
    Route::get('/products-list', [ProductController::class, 'list'])->name('productsList');
    Route::get('/products-company/{company_id}', [ProductController::class, 'getByCompany'])->name('getByCompany');
    Route::get('/api/products/{id}', [ProductAPIController::class, 'getByID'])->name('getProductByID');

    // INVENTORY
    Route::get('/api/inventory-products/product', [InventoryProductAPIController::class, 'getInventoryByProduct'])->name('getInventoryByProduct');
    Route::get('/inventory-product-list', [InventoryProductController::class, 'inventoryProductList'])->name('inventoryProductList');
    Route::get('/inventory-product-export', [InventoryProductController::class, 'exportInventory'])->name('exportInventory');
});

Route::get('/test-connection', function () {
    try {
        DB::connection()->getPdo();

        if (DB::getSchemaBuilder()->hasTable('packagings')) {
            return 'Connection successful! The "packaging" table exists.';
        } else {
            return 'Connection successful !!';
        }
    } catch (\Exception $e) {
        return 'Erreur de connexion : ' . $e->getMessage();
    }
});