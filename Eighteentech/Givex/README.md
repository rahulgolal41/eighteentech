# Shopping cart and checkout page

With the help of this module we will integrate the Givex Api with the Magento to use the giftcards, which is provided from the Givex Api. So with this module we will get the giftcards from givex and update in magento as well as in admin fulfilment grid in Magento with the balance. This module works on shopping cart and checkout page for check the card balance and apply the giftcards on any order. By this module functionality we can purcahse single as well multiple giftcards from authorize store. And also use single and multiple giftcards balance for our shopping experience.


# Features

- Customer can purchase one or more giftcards from the store.

- The customer can see the balance of giftcards code from cart / checkout page and as well as givex/
  checkbalance page.
 
- The customer can apply the giftcards code & it will be applied on cart / checkout page.

- The admin can enable and disable the giftcards module at admin configuration.

- Magento generated giftcards code are not applicable for the shopping.

 
# Installation/Uninstallation [Versions supported: 2.3.x onwards]

**Steps to install module manually in app/code**

- Add directory to app/code/Eighteentech/Givex manually

- bin/magento module:enable Eighteentech_Givex

- bin/magento setup:upgrade

- bin/magento cache:flush

**Steps to uninstall a manually added module in app/code**

- bin/magento module:disable Eighteentech_Givex

- remove directory from app/code/Eighteentech/Givex manually

- bin/magento setup:upgrade

- bin/magento cache:flush


# Configurations

Go to left sidebar of admin dashboard tab "Givex"

Go to Admin -> Stores -> Configuration -> 18THDIGITECH -> Givex

Option to enable/disable module.

Option for the Api credentials

## Contribute

## Support
