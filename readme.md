Adminer Customization
=====================

Customizations for [Adminer](https://www.adminer.org), the best database management tool written in PHP.

- clean design
- visual resolution for production/development servers
- SQL autocomplete plugin
- plugin that dumps to PHP code
- plugin that saves menu scroll position
- plugin that allows magic login with Wasmer (see [Magic Login](#magic-login) section below)

Testing
-------

You can test the plugin locally by running the following command:

```
mkdir data
wasmer run . --net --mapdir=/data:data --env="WASMER_GRAPHQL_URL=https://registry.wasmer.io/graphql"
```

This will start the Adminer application in a Wasmer container and map the current directory to the `/data` directory.

Installation
------------

```
composer require dg/adminer
```

Create file `index.php` somewhere in your document root, for example in subdirectory `adminer/`:

```php
<?php
touch(__DIR__ . '/adminer.css');
require __DIR__ . '/../../vendor/dg/adminer/index.php'; // CHECK THAT THIS PATH IS CORRECT
```

And then navigate your browser to this directory (i.e. `http://localhost/adminer`).


Autologin
---------

If you want to login automatically (use that for non-public environment only, e.g. localhost), you can do that by setting environment variables `ADMINER_SERVER`, `ADMINER_USERNAME` and `ADMINER_PASSWORD`. If you want to be automatically redirected to https, set `ADMINER_HTTPS_REDIRECT` environment variable to `true`.


Magic Login
-----------

The magic login feature allows users to authenticate and connect to databases using a temporary token from the Wasmer backend, without manually entering credentials.

### How It Works

1. **User receives a magic login URL** from the Wasmer platform with the format:
   ```
   https://your-adminer.wasmer.dev/?magiclogin=<token>&dbid=<database_id>
   ```
   Example:
   ```
   https://wordpress-865zz.wasmer.dev/?magiclogin=wott_IC5J7NJ3AYCBI2FXRRKDB37SBR5NS3RI&dbid=db_abc123
   ```

2. **The token is validated** by querying the Wasmer GraphQL API:
   - The token (starting with `wott_`) is a backend authentication token from Wasmer
   - Adminer sends a GraphQL query to `WASMER_GRAPHQL_URL` with the token as Bearer authentication
   - The Wasmer backend validates the token and returns database credentials if the user is authorized

3. **Database credentials are retrieved** from the GraphQL response:
   - Host and port
   - Username and password
   - Database name
   - App metadata (for validation and navigation)

4. **Automatic login** is performed:
   - The credentials are automatically injected into Adminer's authentication system
   - User is logged into the database without seeing the login form
   - A "Go to Wasmer App Dashboard" link is added to the navigation

### Required Environment Variables

- **`WASMER_GRAPHQL_URL`** (required): URL to Wasmer's GraphQL API
  - Example: `https://registry.wasmer.io/graphql`
  - This is where the magic login token is validated

- **`WASMER_APP_ID`** (optional): Restricts database access to a specific app ID
  - When set, only databases belonging to this app can be accessed
  - Provides additional security layer
  - Example: `app_xyz789`

### Troubleshooting

If the magic login link redirects to the login form instead of the admin page:

1. **Check environment variables**: Ensure `WASMER_GRAPHQL_URL` is set correctly
   ```bash
   echo $WASMER_GRAPHQL_URL
   ```

2. **Verify token validity**: The `wott_*` token may be expired or invalid
   - Tokens are temporary and have an expiration time
   - Request a new magic login link

3. **Check authorization**: The user may not have access to the requested database
   - Verify the user has permissions for the database ID in the Wasmer platform

4. **Validate app matching**: If `WASMER_APP_ID` is set, ensure it matches the database's app
   - The database must belong to the configured app

5. **Check for CORS errors**: Look in the browser console for CORS-related errors
   - This may indicate domain configuration issues

### Security Notes

- The magic login token (`wott_*`) is validated by the Wasmer backend, not stored locally
- Token validation happens server-side via the GraphQL API
- Tokens are temporary and expire after a certain time period
- When `WASMER_APP_ID` is configured, only databases from that specific app can be accessed
- The admin URL is stored in a cookie for 1 hour to enable navigation back to the dashboard


Login page
----------

![login page](https://dg.github.io/adminer/images/screenshot1.png)

Tables overview
---------------

![tables overview](https://dg.github.io/adminer/images/screenshot2.png)

Red top border indicates production server
------------------------------------------

![production server](https://dg.github.io/adminer/images/screenshot3.png)

Use `tab` for autocomplete
--------------------------

![autocomplete](https://dg.github.io/adminer/images/screenshot4.png)

Export as PHP code for [Nette Database](https://github.com/nette/database)
--------------------------------------------------------------------------

![export](https://dg.github.io/adminer/images/screenshot5.png?v1)

Export as PHP code for [Nette Forms](https://github.com/nette/forms)
--------------------------------------------------------------------------

![export](https://dg.github.io/adminer/images/screenshot6.png?v1)

![export](https://dg.github.io/adminer/images/screenshot7.png)
