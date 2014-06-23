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