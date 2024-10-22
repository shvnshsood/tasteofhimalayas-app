<?php



Route::group(['as'=> 'admin.', 'prefix' => 'admin'],function (){
    Route::prefix('pos')->group(function() {
        Route::get('/', 'POSController@index')->name('pos');
        Route::get('/load-products', 'POSController@load_products')->name('load-products');
        Route::get('/load-product-modal/{id}', 'POSController@load_product_modal')->name('load-product-modal');
        Route::get('/add-to-cart', 'POSController@add_to_cart')->name('add-to-cart');
        Route::get('/cart-quantity-update', 'POSController@cart_quantity_update')->name('cart-quantity-update');
        Route::get('/remove-cart-item/{id}', 'POSController@remove_cart_item')->name('remove-cart-item');
        Route::get('/cart-clear', 'POSController@cart_clear')->name('cart-clear');

        Route::post('/create-new-customer', 'POSController@create_new_customer')->name('create-new-customer');
        Route::post('/create-new-address', 'POSController@create_new_address')->name('create-new-address');
        Route::post('/place-order', 'POSController@place_order')->name('place-order');
    });
});


