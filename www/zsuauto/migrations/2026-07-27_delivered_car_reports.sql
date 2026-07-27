CREATE TABLE IF NOT EXISTS `zs_delivered_car_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `report_text` MEDIUMTEXT NOT NULL,
  `delivered_at` DATE NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `published_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zs_delivered_car_reports_request` (`request_id`),
  KEY `idx_zs_delivered_car_reports_public` (`is_published`, `delivered_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zs_delivered_car_report_photos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `relative_path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_zs_delivered_car_report_photos_report` (`report_id`, `sort_order`, `id`),
  CONSTRAINT `fk_zs_delivered_car_report_photos_report`
    FOREIGN KEY (`report_id`) REFERENCES `zs_delivered_car_reports` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
