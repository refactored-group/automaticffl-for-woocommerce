# Automatic FFL for WooCommerce

Contributors: refactoredgroup
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: FFL Locator, Firearms, Search, FFL, Guns
Stable tag: 1.0.30
Tested up to: 6.9
Requires at least: 5.6
Requires PHP: 7.0

[![Automatic FFL for WooCommerce](https://www.automaticffl.com/img/logos/logo.png)](https://www.automaticffl.com/)

Automatic FFL is a powerful extension designed to simplify the process of shipping firearms to Federal Firearms License (FFL) dealers.

Mixed-cart banners in Classic and Blocks let shoppers choose which group to check out. The other group is saved and put back in the cart after checkout. Buttons show total quantities with singular/plural wording, and firearm/ammunition notices share a compact design.

## Overview

Automatic FFL for WooCommerce is a powerful extension designed to simplify the process of shipping firearms and ammunition to Federal Firearms License (FFL) dealers. The extension seamlessly integrates with both the classic and block-based WooCommerce checkouts, adding a user-friendly interface that allows customers to select an FFL dealer near them for order pickup. It automatically detects firearms and ammunition in the cart, enforces state-based shipping restrictions, and handles mixed carts containing both regulated and non-regulated items. With this extension, you can ensure compliance with firearms and ammunitions shipping regulations while providing a convenient experience for your customers.

## Features Include

- **Checkout without Application Passwords:** Checkout classification now uses authoritative product facts read inside WooCommerce and the store's existing Automatic FFL restriction rules. A host that disables WordPress Application Passwords no longer blocks the dealer-selection checkout path.

- **Missing-certificate order upload:** A prominent order-page banner alerts store managers when an FFL certificate is missing and lets them upload PDFs, images, or ZIPs. A valid certificate is attached after Automatic FFL finishes processing it.

- **Simplified Firearms and Ammunitions Shipping:** With Automatic FFL for WooCommerce, you can streamline the complex process of shipping firearms and ammunitions to FFL dealers, making it easy to comply with federal regulations.

- **Automatic Detection of FFL-Required Products:** The extension automatically analyzes your cart to identify firearms and ammunition and will trigger an FFL selection when needed.

- **FFL Dealer Map:** An interactive dealer map is displayed at checkout, allowing customers to search for nearby FFL dealers by address or zipcode and select one for order pickup.

- **Classic & Block Checkout Support:** Works with both the traditional WooCommerce checkout and the newer block-based checkout, providing the same dealer selection experience regardless of which checkout your store uses.

- **Validated by the ATF:** Our dealers database updates daily directly from the Bureau of Alcohol, Tobacco, Firearms and Explosives, commonly referred to as the ATF.

- **Mixed Cart Handling & Save for Later:** Classic carts and WooCommerce Cart Blocks display the mixed-cart banner above the full cart and let customers save either group for their next order. Cart Block messages and quantities update with the cart, and ammunition carts include shipping-state selection. Saved items are automatically restored after the order is placed.

- **Ammunition & State Restrictions:** Automatically enforces state-based compliance for ammunition purchases. When a customer's shipping state requires an FFL transfer for ammunition, the extension will prompt dealer selection at checkout.

- **Customization:** We automatically display common FFL dealers, but you have full control over which dealers show to your customers. You can also promote dealers by marking them as preferred or add information to them such as store hours or transfer fees.

- **Same Day Customer Support:** If you need any assistance or have any questions, we are always here to help.

## Demo

- [https://woo80.demos.automaticffl.com/shop/](https://woo80.demos.automaticffl.com/shop/)

## Installation

For installation and setup, please follow the instructions [here](https://www.automaticffl.com/installation/woocommerce/).

## Connection status and troubleshooting

WooCommerce → Automatic FFL shows two separate statuses:

- **Checkout API** checks whether the saved store can reach the direct product-classification service. This check does not validate Google Maps restrictions, live cart extraction, or every shopper interaction.
- **Additional-feature credentials** reports the existing WordPress credential registration used by category browsing, order-level certificate uploads and automatic attachment callbacks, and order reporting. A warning here does not mean direct checkout classification or normal dealer certificate links are unavailable.

If checkout reports that the API needs attention, confirm the saved Store Hash and active subscription, then run **Check checkout connection**. If the result says the backend is in legacy mode, the service must be upgraded before checkout can work without Application Passwords. Google Maps remains a separate required setting for the dealer map.

This release continues to use the category rules already saved in Automatic FFL. Browsing or synchronizing new category names without WordPress credentials, and replacing authentication for order-level certificate/reporting operations, remain separate follow-up work.

## External services

**Google Maps:** used to create the map experience during the checkout

- [Google Maps](https://developers.google.com/maps/)
- [Terms of service](https://cloud.google.com/maps-platform/terms)

**AutomaticFFL:** provides FFL dealer data, an interactive map experience, and product restrictions

When an order using a selected FFL dealer is placed, the plugin sends the WooCommerce order ID, canonical dealer ID, FFL license, and order creation time to Automatic FFL for aggregate order attribution. It does not send buyer information for this reporting.

- [AutomaticFFL](https://www.automaticffl.com/)
- [Privacy Policy](https://www.automaticffl.com/privacy-policy/)

## Support and Documentation

For support, inquiries, or documentation, please visit [https://automaticffl.com/](https://automaticffl.com/).
