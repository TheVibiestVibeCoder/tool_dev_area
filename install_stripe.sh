#!/bin/bash
# Stripe PHP SDK Installation Script

echo "Installing Stripe PHP SDK..."

# Check if composer exists
if command -v composer &> /dev/null; then
    echo "Using Composer to install Stripe..."
    composer require stripe/stripe-php
else
    echo "Composer not found. Downloading Stripe PHP SDK manually..."

    # Download latest Stripe PHP SDK
    if command -v wget &> /dev/null; then
        wget https://github.com/stripe/stripe-php/archive/refs/heads/master.zip -O stripe-php.zip
    elif command -v curl &> /dev/null; then
        curl -L https://github.com/stripe/stripe-php/archive/refs/heads/master.zip -o stripe-php.zip
    else
        echo "Error: Neither wget nor curl found. Please install one of them or use Composer."
        exit 1
    fi

    # Extract
    unzip -q stripe-php.zip
    mv stripe-php-master stripe-php
    rm stripe-php.zip

    echo "Stripe PHP SDK installed successfully!"
fi

echo ""
echo "Installation complete!"
echo ""
echo "Next steps:"
echo "1. Get your Stripe API keys from https://dashboard.stripe.com/apikeys"
echo "2. Update stripe_config.php with your keys"
echo "3. Create products in Stripe Dashboard"
echo "4. Update pricing_config.json with your Stripe price IDs"
