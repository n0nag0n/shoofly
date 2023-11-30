# Shoofly Framework
A Fat-Free Framework core library fork with a few extra features.

### Usage:

First make sure to add a proper url rewrite configuration to your server, see https://fatfreeframework.com/3.6/routing-engine#DynamicWebSites

**Composer:**

```
composer require n0nag0n/shoofly
```

```php
require("vendor/autoload.php");

// Create the app instance
$App = \Shoofly\Base::instance();

// setup a simple route
$App->route('GET /', function() {
	echo 'Hello, world!';
});

// run the dang thing!
$App->run();
```

---
For the main repository (demo package), see https://github.com/bcosca/fatfree  
For the test bench and unit tests, see https://github.com/f3-factory/fatfree-dev  
For the user guide, see https://fatfreeframework.com/user-guide  
For the documentation, see https://fatfreeframework.com/api-reference

### Development:
If you're going to do development on this codebase, you'll need to check the following commands before you commit:
```bash
# Unit tests of course
composer test # or composer phpunit

# Beautify the code
composer beautify # or composer phpcbf

# Check for code style issues
composer style # or composer phpcs
```