Hypercharge Payment module for Magento
=======

The Hypercharge payments interface allows you to offer your customers a wide range of payment methods in your webstore - both off- and online. Sensitive data is kept secure and confidential on the Hypercharge platform.

This module is compatible with Magento 1.4, 1.4.1.1, 1.4.2, 1.5, 1.6, 1.6.1, 1.6.2.0, 1.7, 1.7.0.2., 1.8, 1.8.1, 1.9.0.0 and 1.9.0.1.
It was tested on Magento 1.4.1.1, 1.7.0.2, 1.8.1 on Internet Explorer 8, 9, 10 and 11, Chrome, Mozilla Firefox and Safari.

---------
 1. Installation
---------
 1. Download the ZIP-file from the repository. Unzip the file on your computer and copy the unziped files in your Magento installation – you should see the app, skin and index.php in the root folder. 
 
 2. Login to your Magento admin panel and go under **System > Cache Management**. If cache is enabled select all cache types and flush the cache storage.
 
 3. To check if the module was installed go under **System > Configuration > Advanced** in admin panel and you should see GlobalExperts_Hypercharge entry here.
 
 If the module doesn’t appear please make sure the cache was cleared or the files were correctly copied on your Magento installation and the files permissions are set correctly.
 
---------
 2. Configurations
---------
To configure the payment method go under **System > Configurations > Payment Methods > Hypercharge Channels Configurations**. Here you should add Hypercharge payment channels, each channel on a new line. The channel info should contain the currency code, login, password and channel token.
Payment channels data can be found in your Hypercharge admin panel under **Configuration > Channels**. To get the login and password for each channel click on the information icon next to each channel.

Please be aware that each channel will only work with its corresponding transaction types – for example the credit card channel will not work for PayPal WPF (Web Payment Form) transactions. The best solution is to add all channels from the Hypercharge admin panel in the channels configuration field from Magento – the proper channel will be automatically selected for each payment transaction type.

In the **Hypercharge Channels Configurations** tab you can also enable/disable the debug feature. If this feature is enabled, a *hypercharge.log* file will be created under *var/log* folder; this file will log all the exchanged data between Magento and Hypercharge gateway.
Directly under **Hypercharge Channels Configurations** tab you should see 10 Hypercharge payment types. The first two payments are using Mobile API: Hypercharge Credit Card and Hypercharge Direct Debit. The other 8 payment methods are using the WPF API.

The Mobile API payment methods are PCI compliant – the payment data are transferred directly from customer device to Hypercharge secure servers without the interference of merchant server. Later a notification will be sent to Magento to process the order accordingly.

The WPF payment methods redirect user to Hypercharge servers were the payment data are collected, and after the payment is processed the customer will be redirected to merchant web-store. Also a notification will be sent to Magento to process the order accordingly.

For each payment method you can change the title (**Title**), enable/disable it (**Enabled**), set the payment type on live or test mode (**Test Mode**), set the sort order for frontend payment step list (**Sort order**) and select the billing countries for which the payment method will be available (**Payment from Applicable Countries** and **Payment from Specific Countries**).
For Credit Card payment method you can also select the CC types which will be available (**Credit Card Types**). For all WPF payment methods you can chose to load the Hypercharge payment form – from Hypercharge servers – in an iFrame on your Magento web-store (**Load in iFrame**), instead of redirecting the customer to Hypercharge website; and you can enable the address step for WPF payments (**Show address step on Hypercharge**) – the billing address from Magento will be send automatically in both cases.

---------
3. Testing and configuration in DEMO/LIVE
---------

3.1. For testing of the procces you need to open four new tabs.
   First tab: online webstore - http://www.your-domain.com/ - log in or create new account if you don't have one or checkout as guest
   Second tab: admin panel from the webstore - http://www.your-domain.com/admin - login data (User Name: yourusername Password: yourpassword);
   Third tab: payment register interface - https://sankyudemo.hypercharge.net - login data ( User: yoursankyuusername Password: yoursankyupassword);
   Finaly the fourth tab, will contain your email account, the one which you registered on the webstore, where you will get notifications.

3.2. Channels configuration
   When you make the contract with hypercharge you will be granted with a test account and a live account (sankyu).
   Login into sankyu and go to: Configuration -> Channels, you will have here a list of all channels available.
   In magento you will need to fill in all your channels like this: CURRENCY|LOGIN|PASSWORD|CHANNEL (one channel per row)


3.3. To see the payment method go under System > Configurations > Payment Methods.
   Directly under Hypercharge Channels Configurations tab you should see 15 Hypercharge payment types.
   Some of the payment methods are using Mobile API, other payment methods are using the WPF API.
   The methods that are using Mobile API are:
    - Credit Card
    - Direct Debit
    - SEPA
    - Purchase on Account
    - GTD Purchase on Account-Payolutin
    - GTD Sepa Debit Sale
   As you can see for the moment there are 6 methods that are using Mobile API.
   The others 9 methods are using the WPF API.
   Also, you will be able to see the entire 15 methods, when a new purchase is made. (of course if all are enabled, you have channels for all)
   * Ideal Payment Methos is set by default to specific country Nederland (so it will not appear on frontend if your billing address does not have Nederland)
   * by default, all payment methods are set on testmode
   * all WPF payment methods can loaded in an IFRAME or with redirect
   * please check your PCI Compliance to see if you can load your payment methods in an IFRAME or you need to redirect them to the payment GATEWAY, for this you need to change the "Load in IFRAME" select - visible in all WPF Payments

3.4. To test a payment method, you have to complete few steps:
   - go to the webstore, log in (or checkout as guest), and add something to cart;
   - go to the cart, click "proceed to checkout" to finalize the purchase;
   - set an address and select this address for shipping at shipping step;
   - when the user is informed about shipping rate, click continue;
   - when "payment option" are displayed select the desired method;
   Further, each payment method, has its own steps.

   3.4.1. Credit Card - data that you can use in test mode:
                      Name on Card: random first name and last name;
                      Credit Card Type: select the desired type from dropdown
                      4200000000000000 Visa successful transaction
                      4111111111111111 Visa transaction declined
                      5555555555554444 Master Card successful transaction
                      5105105105105100 Master Card transaction declined
                      For expiration date just set a future date and for CVC just enter 3 random digits.
       Click "Continue", review the order, then click "Place Order".

   For a successful transaction the following four results are needed:
   - On webstore page a success page to be displayed, after placing the order (![screenshot_1](https://github.com/hypercharge/hypercharge-magento/blob/master/screenshot_1.png));
   - in Magento admin > Sales > Orders, the order is registered with status "Pending Payment",
                       and after some time, the order is set to "Processing" (![screenshot_2](https://github.com/hypercharge/hypercharge-magento/blob/master/screenshot_2.png));
   - in Hypercharge > Payments: order is registered with status "Approved" (![screenshot_3](https://github.com/hypercharge/hypercharge-magento/blob/master/screenshot_3.png));
   - when the order status is changed from Pending_Payment to Processing a confirmation email is received (![screenshot_4](https://github.com/hypercharge/hypercharge-magento/blob/master/screenshot_4.png)).

   Also, if you click on (i) info - the last icon on the transaction row, another page with the transaction details,
   will be displayed. Here you will see the transaction details as: payment method, type and mode (![screenshot_5](https://github.com/hypercharge/hypercharge-magento/blob/master/screenshot_5.png)).