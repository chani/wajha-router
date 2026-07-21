<?php

declare(strict_types=1);

/**
 * Safi/Wajha Router
 * Real-World Dataset Benchmark (based on FastRoute's reference dataset)
 */

return [
    'name' => 'RealWorld Dataset Benchmark',
    'routes' => [
        ['method' => 'GET', 'path' => '/', 'handler' => 'home'],
        ['method' => 'GET', 'path' => '/page/{page_slug:[a-zA-Z0-9\-]+}', 'handler' => 'page.show'],
        ['method' => 'GET', 'path' => '/about-us', 'handler' => 'about-us'],
        ['method' => 'GET', 'path' => '/contact-us', 'handler' => 'contact-us'],
        ['method' => 'POST', 'path' => '/contact-us', 'handler' => 'contact-us.submit'],
        ['method' => 'GET', 'path' => '/blog', 'handler' => 'blog.index'],
        ['method' => 'GET', 'path' => '/blog/recent', 'handler' => 'blog.recent'],
        ['method' => 'GET', 'path' => '/blog/post/{post_slug:[a-zA-Z0-9\-]+}', 'handler' => 'blog.post.show'],
        ['method' => 'POST', 'path' => '/blog/post/{post_slug:[a-zA-Z0-9\-]+}/comment', 'handler' => 'blog.post.comment'],
        ['method' => 'GET', 'path' => '/shop', 'handler' => 'shop.index'],
        ['method' => 'GET', 'path' => '/shop/category', 'handler' => 'shop.category.index'],
        ['method' => 'GET', 'path' => '/shop/category/search/{filter_by:[a-zA-Z]+}:{filter_value}', 'handler' => 'shop.category.search'],
        ['method' => 'GET', 'path' => '/shop/category/{category_id:\d+}', 'handler' => 'shop.category.show'],
        ['method' => 'GET', 'path' => '/shop/category/{category_id:\d+}/product', 'handler' => 'shop.category.product.index'],
        ['method' => 'GET', 'path' => '/shop/category/{category_id:\d+}/product/search/{filter_by:[a-zA-Z]+}:{filter_value}', 'handler' => 'shop.category.product.search'],
        ['method' => 'GET', 'path' => '/shop/product', 'handler' => 'shop.product.index'],
        ['method' => 'GET', 'path' => '/shop/product/search/{filter_by:[a-zA-Z]+}:{filter_value}', 'handler' => 'shop.product.search'],
        ['method' => 'GET', 'path' => '/shop/product/{product_id:\d+}', 'handler' => 'shop.product.show'],
        ['method' => 'GET', 'path' => '/shop/cart', 'handler' => 'shop.cart.show'],
        ['method' => 'PUT', 'path' => '/shop/cart', 'handler' => 'shop.cart.add'],
        ['method' => 'DELETE', 'path' => '/shop/cart', 'handler' => 'shop.cart.empty'],
        ['method' => 'GET', 'path' => '/shop/cart/checkout', 'handler' => 'shop.cart.checkout.show'],
        ['method' => 'POST', 'path' => '/shop/cart/checkout', 'handler' => 'shop.cart.checkout.process'],
        ['method' => 'GET', 'path' => '/admin/login', 'handler' => 'admin.login'],
        ['method' => 'POST', 'path' => '/admin/login', 'handler' => 'admin.login.submit'],
        ['method' => 'GET', 'path' => '/admin/logout', 'handler' => 'admin.logout'],
        ['method' => 'GET', 'path' => '/admin', 'handler' => 'admin.index'],
        ['method' => 'GET', 'path' => '/admin/product', 'handler' => 'admin.product.index'],
        ['method' => 'GET', 'path' => '/admin/product/create', 'handler' => 'admin.product.create'],
        ['method' => 'POST', 'path' => '/admin/product', 'handler' => 'admin.product.store'],
        ['method' => 'GET', 'path' => '/admin/product/{product_id:\d+}', 'handler' => 'admin.product.show'],
        ['method' => 'GET', 'path' => '/admin/product/{product_id:\d+}/edit', 'handler' => 'admin.product.edit'],
        ['method' => 'PUT', 'path' => '/admin/product/{product_id:\d+}', 'handler' => 'admin.product.update'],
        ['method' => 'DELETE', 'path' => '/admin/product/{product_id:\d+}', 'handler' => 'admin.product.destroy'],
        ['method' => 'GET', 'path' => '/admin/category', 'handler' => 'admin.category.index'],
        ['method' => 'GET', 'path' => '/admin/category/create', 'handler' => 'admin.category.create'],
        ['method' => 'POST', 'path' => '/admin/category', 'handler' => 'admin.category.store'],
        ['method' => 'GET', 'path' => '/admin/category/{category_id:\d+}', 'handler' => 'admin.category.show'],
        ['method' => 'GET', 'path' => '/admin/category/{category_id:\d+}/edit', 'handler' => 'admin.category.edit'],
        ['method' => 'PUT', 'path' => '/admin/category/{category_id:\d+}', 'handler' => 'admin.category.update'],
        ['method' => 'DELETE', 'path' => '/admin/category/{category_id:\d+}', 'handler' => 'admin.category.destroy'],
    ],
    'requests' => [
        'static_first'    => ['method' => 'GET', 'uri' => '/'],
        'static_last'     => ['method' => 'GET', 'uri' => '/admin/category'],
        'dynamic_first'   => ['method' => 'GET', 'uri' => '/page/hello-world'],
        'dynamic_last'    => ['method' => 'GET', 'uri' => '/admin/category/123'],
        'longest_route'   => ['method' => 'GET', 'uri' => '/shop/category/123/product/search/status:sale'],
        'invalid_method'  => ['method' => 'PUT', 'uri' => '/about-us'],
        'unknown_route'   => ['method' => 'GET', 'uri' => '/shop/product/awesome/nonexistent'],
    ],
];
