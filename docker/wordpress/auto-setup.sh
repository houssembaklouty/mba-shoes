#!/bin/bash
# Auto-setup: activates WooCommerce plugin

set -e

echo "[MBA] Waiting for WordPress to be ready..."
until wp core is-installed --allow-root 2>/dev/null; do
  sleep 3
done

echo "[MBA] WordPress is installed. Setting up WooCommerce..."

# Install and activate WooCommerce
if ! wp plugin is-installed woocommerce --allow-root; then
  wp plugin install woocommerce --activate --allow-root
  echo "[MBA] WooCommerce installed and activated."
else
  if ! wp plugin is-active woocommerce --allow-root; then
    wp plugin activate woocommerce --allow-root
  fi
  echo "[MBA] WooCommerce already installed."
fi

# Set permalink structure
wp rewrite structure '/%postname%/' --allow-root

echo "[MBA] Auto-setup complete."
