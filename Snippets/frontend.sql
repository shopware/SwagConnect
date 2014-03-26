
INSERT INTO `s_core_snippets` (`namespace`, `shopID`, `localeID`, `name`, `value`, `created`, `updated`) VALUES
('frontend/checkout/bepado', 1, 1, 'price_of_product__product_changed_to__price_', 'Der Preis von  %product hat sich auf %price geändert.', '2014-01-11 18:30:18', '2014-01-11 18:30:18'),
('frontend/checkout/bepado', 1, 1, 'availability_of_product__product_changed_to__availability_', 'Die Verfügbarkeit von %product hat sich auf %availability geändert.', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/checkout/bepado', 1, 2, 'price_of_product__product_changed_to__price_', 'Price of product %product changed to %price.', '2014-01-11 18:30:18', '2014-01-11 18:30:18'),
('frontend/checkout/bepado', 1, 2, 'availability_of_product__product_changed_to__availability_', 'Availability of product %product changed to %availability.', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),

('frontend/checkout/bepado', 1, 1, 'frontend_checkout_cart_bepado_phone', 'Um diese Produkte zu bestellen, müssen Sie ihre Telefonnummer hinterlegen. Klicken Sie hier, um diese Änderung jetzt durchzuführen.', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/checkout/bepado', 1, 2, 'frontend_checkout_cart_bepado_phone', 'You need to leave your phone number in order to purchase these products. Click here in order to change your phone number now.', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),

('frontend/detail/bepado', 1, 2, 'bepado_detail_marketplace_article', 'Article from marketplace {$bepadoShop->name}', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/detail/bepado', 1, 2, 'bepado_detail_marketplace_article_implicit', 'Article from storage {$bepadoShop->id}', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/detail/bepado', 1, 1, 'bepado_detail_marketplace_article', 'Marktplatz-Artikel von {$bepadoShop->name}', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/detail/bepado', 1, 1, 'bepado_detail_marketplace_article_implicit', 'Artikel aus Lager {$bepadoShop->id}', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),

('frontend/bepado/shipping_costs', 1, 1, 'bepad_storage_dispatch', 'Lagerversand', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/bepado/shipping_costs', 1, 2, 'bepad_storage_dispatch', 'Shipping from storage', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),

('frontend/bepado/shipping_costs', 1, 1, 'bepado_dispatch_shop_name', 'Versand von »{$item.shopInfo.name}«', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/bepado/shipping_costs', 1, 2, 'bepado_dispatch_shop_name', 'Shipping from »{$item.shopInfo.name}«', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),

('frontend/bepado/shipping_costs', 1, 1, 'bepado_dispatch_shop_id', '}Versand für Lager {$item.shopInfo.id}', '2014-01-11 18:32:48', '2014-01-11 18:32:48'),
('frontend/bepado/shipping_costs', 1, 2, 'bepado_dispatch_shop_id', '}Shipping from storage {$item.shopInfo.id}', '2014-01-11 18:32:48', '2014-01-11 18:32:48')


ON DUPLICATE KEY UPDATE
  `namespace` = VALUES(`namespace`),
  `shopID` = VALUES(`shopID`),
  `name` = VALUES(`name`),
  `localeID` = VALUES(`localeID`)
;
