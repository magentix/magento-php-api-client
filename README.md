# Simple PHP Magento API Client

## Installation

```shell
composer require magentix/magento-php-api-client
```

## Authorisation

Create a new authorisation in Magento Admin:

*System > Extension > Integration*

## Usage

```php
$consumerKey = 'll6io7s7pzyvzaulktzplenwsfn8udgx';
$consumerSecret = 'r0xw7zpd5i5n2io4ez3lw59pxfjlhsvw';
$accessToken = '0bfsszueo0zo6lzo7u4cckh56rzyhokq';
$accessTokenSecret = 'ntird82zskvmc4boo40q8wbsugt948vt';

$client = new \Magentix\MagentoApiClient\MagentoApiClient(
    $consumerKey,
    $consumerSecret,
    $accessToken,
    $accessTokenSecret
);
```

```php
$method = 'GET';
$url = 'https://www.example.com/index.php/rest/all/V1/products/SKU0001';

$client->request($method, $url);
```

```php
$method = 'PUT';
$url = 'https://www.example.com/index.php/rest/all/V1/products/SKU0001';
$body = [
    'product' => [
        'name' => 'My Product',
    ]
];

$client->request($method, $url, $body);
```

```php
$method = 'GET';
$url = 'https://www.example.com/index.php/rest/all/V1/products';
$params = [
    'searchCriteria' => [
        'filter_groups' => [
            [
                'filters' => [
                    [
                        'field' => 'status',
                        'value' => '1',
                        'condition_type' => 'eq',
                    ]
                ]
            ]
        ]
    ]
];
$body = [];

$client->request($method, $url, $body, $params);
```