Fundraising Nightly Currency Conversion Tool

Usage:

- Call the script with no arguments to retrieve the currency conversion rates and store them in the database
- Call the script with two arguments to get a single conversion (eg, currency.php JPY 5000)
- Call the script with an array as an argument to get an array of conversions (eg, currency.php array( 'JPY 5000', 'CZK 62.5' ) )
- You can also load it onto a webserver and use $_GET variables instead of $argv (eg, currency.php?JPY=5000)

