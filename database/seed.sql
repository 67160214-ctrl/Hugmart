USE `hugmart_db`;

-- Passwords are hashed using password_hash('Password123!', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `email`, `phone`, `password_hash`, `full_name`, `role`, `status`, `email_verified_at`) VALUES
(1, 'admin@hugmart.com', '0810000000', '$2y$10$wE9K2j3R4k5L6m7N8o9P0e1F2G3H4I5J6K7L8M9N0O1P2Q3R4S5T', 'Hugmart Admin', 'admin', 'active', NOW()),
(2, 'seller@hugmart.com', '0820000000', '$2y$10$wE9K2j3R4k5L6m7N8o9P0e1F2G3H4I5J6K7L8M9N0O1P2Q3R4S5T', 'Green Organic Official', 'seller', 'active', NOW()),
(3, 'buyer@hugmart.com', '0830000000', '$2y$10$wE9K2j3R4k5L6m7N8o9P0e1F2G3H4I5J6K7L8M9N0O1P2Q3R4S5T', 'Somchai Jaidee', 'buyer', 'active', NOW());

INSERT INTO `seller_profiles` (`id`, `user_id`, `store_name`, `description`, `status`, `commission_rate`, `rating_avg`) VALUES
(1, 2, 'HugOrganic Official Store', 'ร้านค้าสินค้าออร์แกนิกและไลฟ์สไตล์เพื่อสุขภาพ', 'approved', 3.00, 4.90);

INSERT INTO `categories` (`id`, `name`, `slug`, `icon`) VALUES
(1, 'สินค้าออร์แกนิก & สุขภาพ', 'organic-health', 'fa-leaf'),
(2, 'เครื่องใช้ในบ้านประหยัดพลังงาน', 'home-eco', 'fa-house-tree'),
(3, 'แฟชั่นจากเส้นใยธรรมชาติ', 'eco-fashion', 'fa-shirt');

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `slug`, `description`, `original_price`, `current_price`, `total_stock`, `sold_count`, `rating_avg`, `is_flash_sale`, `flash_sale_price`, `status`) VALUES
(1, 1, 1, 'ชาสมุนไพรออร์แกนิก HugHerbs 100g', 'organic-herbal-tea-100g', 'ชาสมุนไพรปลูกด้วยวิธีธรรมชาติ ไม่ใช้สารเคมี ช่วยผ่อนคลายและบำรุงสุขภาพ', 350.00, 290.00, 100, 45, 5.00, 1, 250.00, 'active'),
(2, 1, 2, 'แก้วน้ำเก็บความเย็น Stainless 304 Eco Hug', 'eco-stainless-tumbler', 'เก็บความเย็นได้นาน 24 ชั่วโมง ลดการใช้พลาสติกครั้งเดียวทิ้ง', 590.00, 450.00, 200, 120, 4.80, 0, NULL, 'active');

INSERT INTO `product_images` (`product_id`, `image_url`, `is_primary`) VALUES
(1, 'assets/uploads/products/tea-1.jpg', 1),
(2, 'assets/uploads/products/tumbler-1.jpg', 1);

INSERT INTO `coupons` (`code`, `type`, `value`, `min_spend`, `start_date`, `end_date`, `is_active`) VALUES
('HUGWELCOME100', 'fixed_amount', 100.00, 500.00, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1);