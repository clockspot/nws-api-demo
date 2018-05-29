# nws-api-demo

PHP demo for the National Weather Service API. Requires cURL support. [Live demo!](https://theclockspot.com/weather)

Simply load it up on your webserver, duplicate `settings-sample.php` to `settings.php` and edit to suit, and host from `/docroot`.

The important bit is the `callAPI()` function and the endpoints passed to it – the rest is all presentation.