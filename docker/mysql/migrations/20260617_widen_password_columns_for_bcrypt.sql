-- Widen password columns to accommodate bcrypt hashes (60 chars)
ALTER TABLE `oc_user` MODIFY COLUMN `password` varchar(255) NOT NULL;
ALTER TABLE `oc_customer` MODIFY COLUMN `password` varchar(255) NOT NULL;
