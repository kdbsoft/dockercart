[mysqld]
innodb_buffer_pool_size = 2G
innodb_buffer_pool_instances = 2
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_log_file_size = 512M
innodb_log_buffer_size = 32M
innodb_io_capacity = 1000
innodb_io_capacity_max = 2000
table_open_cache = 4000
table_definition_cache = 2000
tmp_table_size = 64M
max_heap_table_size = 64M
max_connections = 200
skip-name-resolve
