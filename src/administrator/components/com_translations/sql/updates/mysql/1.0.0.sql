--
-- Brings an existing database up to the 1.0.0 schema.
--

ALTER TABLE `#__translations_queue_states`
    ADD COLUMN `source_version_id` int unsigned NOT NULL DEFAULT 0 /** CAN FAIL **/;
ALTER TABLE `#__translations_queue_states`
    ADD COLUMN `machine_draft` mediumtext /** CAN FAIL **/;

ALTER TABLE `#__translations_feedback` MODIFY `source_text` mediumtext NOT NULL;
ALTER TABLE `#__translations_feedback` MODIFY `machine_draft` mediumtext NOT NULL;
ALTER TABLE `#__translations_feedback` MODIFY `human_correction` mediumtext NOT NULL;
ALTER TABLE `#__translations_feedback` MODIFY `diff_data` mediumtext;

ALTER TABLE `#__translations_feedback`
    ADD COLUMN `source_origin` varchar(20) NOT NULL DEFAULT 'distilled' /** CAN FAIL **/;

ALTER TABLE `#__translations_rules`
    ADD COLUMN `source_term_standard` varchar(255) DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__translations_rules`
    ADD INDEX `idx_source_term_standard` (`source_term_standard`) /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__translations_standard_forms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `language` char(7) NOT NULL,
  `word` varchar(255) NOT NULL,
  `standard_form` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_language_word` (`language`, `word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
