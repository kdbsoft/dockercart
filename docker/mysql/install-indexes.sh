#!/bin/bash
# DockerCart Filter - Auto-create required indexes
# Usage: bash install-indexes.sh

MARIADB_HOST=${MARIADB_HOST:-"mariadb"}
MARIADB_USER=${MARIADB_USER:-"dockercart"}
MARIADB_PASSWORD=${MARIADB_PASSWORD:-"dockercart_password"}
MARIADB_DATABASE=${MARIADB_DATABASE:-"dockercart"}
MARIADB_PORT=${MARIADB_PORT:-3306}
DB_PREFIX=${DB_PREFIX:-"oc_"}

echo "🚀 Installing critical indexes for DockerCart Filter..."
echo "Database: $MARIADB_DATABASE on $MARIADB_HOST:$MARIADB_PORT"
echo ""

# Function to execute SQL
execute_sql() {
    mysql -h "$MARIADB_HOST" -u "$MARIADB_USER" -p"$MARIADB_PASSWORD" -P "$MARIADB_PORT" "$MARIADB_DATABASE" -e "$1"
}

# Check connection
if ! execute_sql "SELECT 1;" &>/dev/null; then
    echo "❌ Cannot connect to MySQL. Check credentials."
    exit 1
fi

echo "✅ Connected to MySQL"
echo ""

# Array of SQL statements
declare -a SQL_STATEMENTS=(
    # Product table - Critical
    "CREATE INDEX idx_product_status_manufacturer ON ${DB_PREFIX}product (status, manufacturer_id)"
    "CREATE INDEX idx_product_status_price ON ${DB_PREFIX}product (status, price)"
    "CREATE INDEX idx_product_manufacturer_status ON ${DB_PREFIX}product (manufacturer_id, status)"
    
    # Product to Category - Critical
    "CREATE INDEX idx_p2c_category_product ON ${DB_PREFIX}product_to_category (category_id, product_id)"
    "CREATE INDEX idx_p2c_product_category ON ${DB_PREFIX}product_to_category (product_id, category_id)"
    
    # Product Attribute - Critical
    "CREATE INDEX idx_pa_product_attribute ON ${DB_PREFIX}product_attribute (product_id, attribute_id, language_id)"
    "CREATE INDEX idx_pa_attribute_product ON ${DB_PREFIX}product_attribute (attribute_id, product_id, language_id)"
    "CREATE INDEX idx_pa_attribute_text ON ${DB_PREFIX}product_attribute (attribute_id, text, language_id)"
    
    # Product Option Value - Critical
    "CREATE INDEX idx_pov_product_option ON ${DB_PREFIX}product_option_value (product_id, option_id)"
    "CREATE INDEX idx_pov_option_value ON ${DB_PREFIX}product_option_value (option_id, option_value_id)"
    
    # Product Special - Important
    "CREATE INDEX idx_ps_customer_date ON ${DB_PREFIX}product_special (customer_group_id, date_start, date_end)"
    "CREATE INDEX idx_ps_customer_product ON ${DB_PREFIX}product_special (customer_group_id, product_id)"
    
    # Manufacturer - Good to have
    "CREATE INDEX idx_manufacturer_name ON ${DB_PREFIX}manufacturer (name)"
    
    # Additional optimization
    "CREATE INDEX idx_product_sort_order ON ${DB_PREFIX}product (sort_order, product_id)"
)

# Install each index
count=0
for sql in "${SQL_STATEMENTS[@]}"; do
    # Check if index already exists (try to create, if exists ignore error)
    if execute_sql "$sql;" 2>&1 | grep -q "Duplicate key name\|already exists"; then
        echo "⏭️  Index already exists: $sql"
    elif execute_sql "$sql;" 2>&1 | grep -q "ERROR"; then
        echo "❌ Error creating index: $sql"
        echo "   Output: $(execute_sql "$sql;" 2>&1)"
    else
        echo "✅ Index created: ${sql#*ON }" 
        ((count++))
    fi
done

echo ""
echo "═══════════════════════════════════════════════════"
echo "📊 Index Installation Complete!"
echo "   Created/Verified: $count indexes"
echo ""

# Show current indexes
echo "📋 Verifying indexes on critical tables..."
echo ""

execute_sql "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$MARIADB_DATABASE' AND (TABLE_NAME = '${DB_PREFIX}product' OR TABLE_NAME = '${DB_PREFIX}product_attribute' OR TABLE_NAME = '${DB_PREFIX}product_option_value' OR TABLE_NAME = '${DB_PREFIX}product_to_category') ORDER BY TABLE_NAME, INDEX_NAME;"

echo ""
echo "═══════════════════════════════════════════════════"
echo "✨ Setup complete! Run optimization:"
echo ""
echo "   OPTIMIZE TABLE ${DB_PREFIX}product;"
echo "   OPTIMIZE TABLE ${DB_PREFIX}product_attribute;"
echo "   OPTIMIZE TABLE ${DB_PREFIX}product_to_category;"
echo "   ANALYZE TABLE ${DB_PREFIX}product;"
echo ""
echo "═══════════════════════════════════════════════════"
