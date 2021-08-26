# Stock Upload # 

![phpcs](https://github.com/DominicWatts/StockUpload/workflows/phpcs/badge.svg)

![PHPCompatibility](https://github.com/DominicWatts/StockUpload/workflows/PHPCompatibility/badge.svg)

![PHPStan](https://github.com/DominicWatts/StockUpload/workflows/PHPStan/badge.svg)

![php-cs-fixer](https://github.com/DominicWatts/StockUpload/workflows/php-cs-fixer/badge.svg)

# Install instructions #

`composer require dominicwatts/stockupload`

`php bin/magento setup:upgrade`

# Usage instructions #

Managed within admin

Content > Csv >
  - Stock Import

Once import queue has been built stock can be inserted a couple of ways

## Submit screen ##

![Submit](https://i.snipboard.io/oadeSf.jpg)

## Console commnad ## 

`xigen:stock:import <import>`

`php bin/magento xigen:stock:import import`