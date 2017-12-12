INSERT INTO s_plugin_connect_categories (id, category_key, label, shop_id) VALUES (2222, "/deutsch", "Deutsch", 1234);
INSERT INTO s_plugin_connect_categories (id, category_key, label, shop_id) VALUES (3333, "/deutsch/test1", "Test 1", 1234);
INSERT INTO s_plugin_connect_categories (id, category_key, label, shop_id) VALUES (4444, "/deutsch/test2", "Test 2", 1234);

INSERT INTO s_categories (id, parent, description, `left`, `right`, `level`, added, changed, active, blog, hidefilter, hidetop)
VALUES (2222, 1, "Deutsch", 1, 1, 1, NOW(), NOW(), 1, 1, 0, 0);

INSERT INTO s_categories_attributes (categoryID, connect_imported_category) VALUES (2222, 1);

INSERT INTO s_plugin_connect_categories_to_local_categories (remote_category_id, local_category_id, stream) VALUES (2222, 2222, "Teststream");