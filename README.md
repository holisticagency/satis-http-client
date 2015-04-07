# satis-http-client
Dedicated http client for satis deployment

This lirary helps to deploy composer repositories on a web server.

add the code below to your `composer.json` file :
```json
"require": {
    "holisticagency/satis-http-client": "~1.0@alpha"
}
`````

add the code below in a PHP file :
```php
use holisticagency\satis\utilities\SatisHttpClient;

//Open repository
$client = new SatisHttpClient('http://domain.tld');
//Private repository (recommanded)
$client = new SatisHttpClient('http://domain.tld', array('user', 'password'));
//Retrieve the configuration
echo $client->getFile()->status()."\n";
echo $client->body()."\n";
// ...Code to eventually modify the configuration
// Update the configuration
echo $client->putFile()->status()."\n";
// ...Build a repository locally
// Update the repository with a bundled archive zip file
echo $client->putBundleZip($zipPath)->status()."\n";
// ...or put files one by one
echo $client->putDir($workingDir)->status()."\n";
```

`$zipPath` is a zip file containing the result of a `satis build` command.

`$workingDir` is where, locally, the result of a `satis build` is done. It is intended to contain a set of files like `packages.json`.

Finally, It is supposed to avoid sending every kind of files but Json or Zip files. It is strongly recommanded to check and set the web server parameters.
