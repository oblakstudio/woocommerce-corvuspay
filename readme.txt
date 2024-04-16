=== CorvusPay WooCommerce Payment Gateway ===
Contributors: Corvus Pay d.o.o.
Tags: payment, credit card, corvuspay, woocommerce
Requires at least: 4.0
Tested up to: 6.4.3
Stable tag: 2.5.7
Requires PHP: 5.6
License: GNU General Public License v2.0 (or later)
License URI: http://www.gnu.org/licenses/gpl-2.0.html

CorvusPay WooCommerce Payment Gateway provides a completely integrated checkout experience between WooCommerce and CorvusPay.

== Description ==

CorvusPay is a flexible, maximally reliable and a highly available Internet Payment Gateway service for card payments in web shops, verified through millions of transactions processed for hundreds of merchants in Croatia and the region!

= How does the CorvusPay service help you grow your business? =

No matter if your company is small, medium-sized or large, if it sells products or services, in today’s age of ubiquitous digitalisation selling through online channels is a must. In order to sell anything online, one must offer various payment methods, including card payments which allow:

* selling through a shopping basket system or payment link generators,
* 24/7/365 sales to buyers across the globe,
* secure collection of payments in real time and multiple currencies,
* convenient installment payments.

= Security comes first! =

The Payment Card Industry Data Security Standard (PCI DSS) is the highest information security standard for organisations which store, process or transfer payment card and cardholder data. It has been defined by the largest card schemes on a global level.

CorvusPay has held a PCI DSS Level 1 certificate since 2012 and renews it successfully every year!

Why is security our biggest investment?

* To protect our buyers against personal and card data theft!
* To help merchants build buyer loyalty for their web shops!

= Support for popular payment card brands! =

CorvusPay offers support for all popular payment card brands: MasterCard, Maestro, Visa, American Express, Diners and Discover.

= CorvusWallet =

FASTER AND MORE SECURE ONLINE PAYMENTS AND PAYMENT COLLECTION!

Once saved, payment card and cardholder data enable buyers to complete transactions faster and help merchants achieve better conversion rates.

For additional information about CorvusWallet service, which is supported with the current version of plugin, please visit [https://www.corvuspay.com/privatni/](https://www.corvuspay.com/privatni/).

If you want to enable CorvusWallet payment method in your web shop, please contact us on email [info@corvuspay.com](mailto:info@corvuspay.com) or phone [+385 1 6389 441](tel:+38516389441).

For more info about CorvusPay please visit [www.corvuspay.com](https://www.corvuspay.com/ "CorvusPay").

== Frequently Asked Questions ==

= What does the transaction flow through the CorvusPay system look like? =

The transaction flow through the CorvusPay system goes like this:

1. a buyer in the web shop selects a product or a service he or she wants to pay for with a card.
2. the buyer is redirected from the merchant’s site to the CorvusPay payment form (site) where he or she enters the data necessary for the completion of the card transaction.
3. CorvusPay sends an authorisation request to the bank.
4. the bank sends a response to the authorisation request submitted by CorvusPay.
5. CorvusPay redirects the buyer to either the ‘cancel’ or the ‘success’ url defined by the merchant, depending on the transaction outcome.

= How do the merchants that use CorvusPay manage their accounts and transactions? =

In accordance with instructions from Corvus, merchants themselves set up their test and production accounts in the CorvusPay merchant interface through which they have an insight into the information about each transaction. CorvusPay also offers the option of integrating transaction management into the merchant application business logic (CRM, CMS) via API mode.

= Which payment card brands are supported in the CorvusPay IPG? =

All major payment card brands are supported in CorvusPay: American Express, Diners, Discover, Maestro, MasterCard and Visa.

= Does CorvusPay enable foreign currency payments? =

Payment in web shops, when accepted through Croatian banks, is only available in the local currency, i.e. in HRK.

Payment in web shops, when accepted through banks from Bosnia and Herzegovina and Serbia, is only possible in domicile currencies, i.e. in BAM (the Bosnian and Herzegovinian market) and RSD (the Serbian market).

To provide processing services for transactions in other foreign currencies (possible only for companies registered in the European Union) CorvusPay is affiliated with Wirecard.

= Does CorvusPay support instalment payments? =

Instalment payments are possible through CorvusPay only by cards issued by certain Croatian banks. Contact us for details.

= What is the difference between preauthorisation and authorisation? =

Authorisation is a type of transaction that does not require additional confirmation from the merchant for debiting the buyer's card, as it is performed in real time.

Preauthorisation is a type of transaction through which the funds on the buyer’s card are reserved, but are not released until the merchant confirms (completes) the transaction. It is used in case the merchant needs to check whether the ordered goods are in stock. The deadline for the preauthorisation completion depends on the bank that issued the card. Once the preauthorisation expires, payment collection is not possible anymore, and the buyer must go through the payment process again. It is recommended that transactions be completed within 7 days.

= Does CorvusPay offer functionalities based on card data storage? =

CorvusPay allows card data storage, which enables the implementation of advanced functionalities, such as:

* Tokenization: the buyer saves their card data, so the merchant can initiate the transaction at a later time,
* CorvusWallet: the buyer saves their card data, and can pay with their stored cards in all web shops that support CorvusWallet as a payment method.

= What is an SSL certificate and does a web shop using CorvusPay need an SSL certificate? =

The SSL certificate is a piece of web server-side code that enables secure exchange of information between the website visitors and the server where the site is located, and contributes to the overall user confidence in the web address and the merchant's website. When the web browser displays web pages, the SSL certificate provides encrypted connection to the server. Thanks to the SSL certificate, personal data and buyer messages remain protected. In addition to online security, SSL certificates also provide added confidence to buyers because they contain information that confirms the validity of the company.

Since the buyer enters all sensitive card data into the CorvusPay payment form, which is ensured by a high level of SSL encryption (256-bit encryption), the merchant does not come into contact with card data, is not responsible for its transfer or storage, so he or she is not required to have an SSL certificate. Additionally, all stored user data is protected by strong cryptography, using a FIPS 140-2 Level 3 certified cryptographic device.

SSL is, however, recommended to web shops, as some Internet browsers display a warning message to buyers the moment they switch from a page secured by an SSL certificate (exposed through https: //) to a page that is not secured. Although harmless, an alert message that appears when a buyer, having entered sensitive card data into the CorvusPay payment form, returns to the merchant's online point of sale, can often be confusing and does not instill trust, which is why we recommend our clients to install an SSL certificate. If the sole purpose of having an SSL certificate is to disable the above mentioned warning message, a certificate with a minimum verification level is sufficient.

= How long does a web merchant have to wait to receive the payment for the buyer’s order? =

CorvusPay is neither a bank nor a company offering banking services, meaning that it neither has access to buyer accounts nor does it transfer money from accounts. Payment terms and due dates are to be negotiated directly with the bank / card scheme, and they usually depend on the card brand used in the transaction.

= Does a web merchant need to fiscalize invoices for goods / services charged through the CorvusPay system? =

According to the Cash Transaction Fiscalization Law, cards are a payment method which falls within the scope of cash payments, which means they are subject to fiscalization. Corvus does not offer an invoice fiscalization solution at the moment.

== Screenshots ==

1. The settings panel with default settings.
2. The settings panel with advanced settings.
3. CorvusPay MerchantCenter Store ID and Secret Key settings.
4. CorvusPay MerchantCenter success and cancel URL settings.
5. Standard checkout with CorvusPay.
6. Checkout with tokenization.
7. Checkout using stored credit card.
8. CorvusPay payment form.
9. CorvusPay Wallet login.
10. CorvusPay Wallet stored credit card.

== Changelog ==

= 2.5.7 =
* Added support for cURL compiled with NSS and GnuTLS.

= 2.5.6 =
* Added validation for empty advanced installments, translation and performance improvements.

= 2.5.5 =
* Updated logo.

= 2.5.4 =
* Duplicate order number fix.

= 2.5.3 =
* Fixed price on success page when using advanced installments.

= 2.5.2 =
* Added support for multiple partial refund.

= 2.5.1 =
* Updated corvuspay php sdk version to 1.4.1.

= 2.5.0 =
* Updated corvuspay api version to 1.4.

= 2.4.2 =
* Tested for WP 6.0 and fixed translations.

= 2.4.1 =
* PHP SDK fix for single quote in products.

= 2.4.0 =
* Added support for capture charges and void transaction.
* Fixed certificate errors.
* Added filter for modifying order parameters.
* Added response message, approval code and transaction date and time to thank you page and emails.
* Added support for custom order number format.
* Added notification for invalid time limit settings.
* Fix for duplicate tokens.
* PHP SDK integration.

= 2.3.7 =
* Fix for cart when paying with token.
* Fix for duplicate tokens.

= 2.3.6 =
* Fixed WooCommerce Subscriptions.

= 2.3.5 =
* Fixed hide tab functionality.

= 2.3.4 =
* Added hide tab functionality.
* Fixed supported languages.

= 2.3.3 =
* Upgraded to CorvusPay API version 1.3.

= 2.3.2 =
* Fixed a bug in currency based routing.
* Added RSD currency

= 2.3.1 =
* Fixed a bug in PIS payments configuration.

= 2.3.0 =
* Added currency based routing support.
* Fixed Apache ModSecurity errors.
* Increased Description field size.

= 2.2.1 =
* Fixed orders which contain double or single quotes.

= 2.2.0 =
* Added CorvusPay by IBAN support.
* Added support for new currencies.
* Fixed wrong CorvusPay icon size on some browsers.
* Minor code improvements and bug fixes.

= 2.1.1 =
* Fixed API certificate settings.
* Fixed test store name.

= 2.1.0 =
* Supports tokenization (requires API certificate)
* Supports WooCommerce Subscriptions (requires API certificate)
* Supports refunds (requires API certificate)

= 2.0.0 =
* Complete rewrite
* Uses new payment form with CorvusPay Wallet support.
* Uses WooCommerce WC_API for success and cancel URLs
* Redirects using HTTP redirects for smoother experience
* Added payment time limit option
* Added advanced installments with discounts option

= 1.1 =
* Fixed redirects

= 1.0 =
* First version of CorvusPay WooCommerce integration

== Upgrade Notice ==

= 2.0.0 =

Version 2.0.0 is a major release. Please do a full site backup and test on a staging site before deploying to a live/production server.