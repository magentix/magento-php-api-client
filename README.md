# Simple PHP Magento API Client

One class without dependency

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

## Cache

```php
$cache = new MagentoApiCache(3600, __DIR__ . DIRECTORY_SEPARATOR . 'api_cache');

$client = new \Magentix\MagentoApiClient\MagentoApiClient(
    $consumerKey,
    $consumerSecret,
    $accessToken,
    $accessTokenSecret,
    $cache
);
```

### GET

```php
$url = 'https://www.example.com/index.php/rest/all/V1/products/SKU0001';

$client->get($url);
```

```php
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
    ],
    'fields' => 'items[id,name,custom_attributes]',
];

$client->get($url, $params);
```

### DELETE

```php
$url = 'https://www.example.com/index.php/rest/all/V1/products/SKU0001';

$client->delete($url);
```

### PUT

```php
$url = 'https://www.example.com/index.php/rest/all/V1/products/SKU0001';
$data = [
    'product' => [
        'name' => 'My Product',
    ]
];

$client->put($url, $data);
```

### POST

```php
$url = 'https://www.example.com/index.php/rest/all/V1/products';
$data = [
    'product' => [
        'sku' => 'SKU0001',
        'name' => 'My Product',
        'price' => '10.00',
        'attribute_set_id' => 4,
        'status' => 1,
        'visibility' => 4,
        'type_id' => 'simple',
        'weight' => 0.1,
        'extension_attributes' => [
            'website_ids' => [1],
            'stock_item' => [
                'qty' => 100,
                'is_in_stock' => true,
            ]
        ],
        'custom_attributes' => [
            [
                'attribute_code' => 'url_key',
                'value' => 'my-product',
            ],
            [
                'attribute_code' => 'category_ids',
                'value' => [4],
            ]
        ]
    ]
];

$client->post($url, $data);
```
