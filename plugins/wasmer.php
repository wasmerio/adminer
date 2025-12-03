<?php

/**
 * Execute a GraphQL query against the Wasmer registry
 * 
 * This function sends a GraphQL query to the Wasmer backend API to fetch
 * database credentials and metadata. It's used by the magic login system
 * to authenticate and retrieve database connection information.
 * 
 * @param string $registry The GraphQL endpoint URL (e.g., https://registry.wasmer.io/graphql)
 * @param string $query The GraphQL query string
 * @param array $variables Variables to be used in the GraphQL query
 * @param string|null $authToken Optional Bearer token for authentication (the magic login token)
 * @return array|null The decoded JSON response, or NULL on error
 */
function wasmer_graphql_query($registry, $query, $variables, $authToken = NULL)
{
    // Prepare the payload
    $payload = json_encode([
        "query" => $query,
        "variables" => $variables
    ]);
    $authHeader = $authToken ? "Authorization: Bearer $authToken\r\n" : NULL;
    // Set up the HTTP context
    $options = [
        'http' => [
            'header'  => implode("\r\n", array_filter([
                "Content-Type: application/json",
                "Accept: application/json",
                $authHeader
            ])),
            'method'  => 'POST',
            'content' => $payload,
        ],
    ];

    $context = stream_context_create($options);

    // Send the request
    $response = file_get_contents($registry, false, $context);

    // Handle errors
    if ($response === FALSE) {
        return NULL;
    }

    // Decode the JSON response
    $responseData = json_decode($response, true);
    return $responseData;
}

/**
 * AdminerWasmer Plugin
 * 
 * This plugin implements the "magic login" feature for Adminer, which allows
 * users to authenticate and connect to databases using a temporary token
 * provided by the Wasmer backend.
 * 
 * MAGIC LOGIN FLOW:
 * =================
 * 1. User accesses URL with format: ?magiclogin=<token>&dbid=<database_id>
 * 2. The token (starting with "wott_") is a backend authentication token from Wasmer
 * 3. Plugin makes GraphQL query to WASMER_GRAPHQL_URL with token as Bearer auth
 * 4. GraphQL API validates token and returns database credentials if authorized
 * 5. Plugin validates database belongs to WASMER_APP_ID (if configured)
 * 6. Database credentials are automatically injected into Adminer's login form
 * 7. User is logged into the database automatically without manual credentials
 * 
 * REQUIRED ENVIRONMENT VARIABLES:
 * ===============================
 * - WASMER_GRAPHQL_URL: URL to Wasmer's GraphQL API (e.g., https://registry.wasmer.io/graphql)
 * - WASMER_APP_ID (optional): Restricts access to databases from a specific app ID for security
 * 
 * URL PARAMETERS:
 * ===============
 * - magiclogin: The authentication token (e.g., wott_IC5J7NJ3AYCBI2FXRRKDB37SBR5NS3RI)
 * - dbid: The database ID to connect to
 * 
 * TROUBLESHOOTING:
 * ================
 * If magic login redirects to login form instead of admin page:
 * - Verify WASMER_GRAPHQL_URL environment variable is set correctly
 * - Check that the token is valid and not expired
 * - Ensure the user has access to the requested database
 * - Verify WASMER_APP_ID matches the database's app (if set)
 * - Check for CORS errors in browser console
 */
class AdminerWasmer
{
    private $wasmerAppId;

    function __construct()
    {
        // Load the app ID from environment to restrict database access to a specific app
        $this->wasmerAppId = getenv("WASMER_APP_ID");

        // Check if this is a magic login request
        // Magic login requires: magiclogin token, dbid parameter, and NO username (to avoid conflicts)
        if (isset($_GET["magiclogin"]) && isset($_GET["dbid"]) && !isset($_GET["username"])) {
            // Get the Wasmer GraphQL API URL from environment
            $url = getenv("WASMER_GRAPHQL_URL");
            if (!$url) {
                die('Error while doing Magic Login: WASMER_GRAPHQL_URL environment variable is not set.');
            }
            
            // Extract the magic login token from the URL parameter
            // This token (e.g., wott_IC5J7NJ3AYCBI2FXRRKDB37SBR5NS3RI) is a backend authentication token
            // It will be used as a Bearer token when querying the Wasmer GraphQL API
            $authToken = $_GET["magiclogin"];
            
            // GraphQL query to fetch database credentials
            // The token is validated on the backend, and if authorized, returns:
            // - Database connection details (host, port, username, name, password)
            // - App information (id, adminUrl) for validation and navigation
            $query = <<<'GRAPHQL'
            query ($dbid: ID!) {
                node(id: $dbid) {
                    ... on AppDatabase {
                        id
                        host
                        port
                        username
                        name
                        password
                        app {
                            id
                            adminUrl
                        }
                    }
                }
            }
            GRAPHQL;
            $variables = [
                "dbid" => $_GET["dbid"]
            ];
            
            // Execute the GraphQL query with the magic login token
            $responseData = wasmer_graphql_query($url, $query, $variables, $authToken);
            // Check if the GraphQL query was successful
            if (!$responseData) {
                die('Error while doing Magic Login: Error occurred while fetching the database credentials.');
            }

            // Extract node data from the GraphQL response
            $nodeData = isset($responseData['data']['node']) ? $responseData['data']['node'] : null;

            // Validate that the database was found and user has access
            // The backend validates the token and only returns data if authorized
            if ($nodeData === null) {
                die('Error while doing Magic Login: Database with the provided id couldn\'t be found, or you don\'t have access to it.');
            }

            // Security check: If WASMER_APP_ID is configured, ensure the database
            // belongs to this specific app. This prevents access to databases from other apps.
            if ($this->wasmerAppId && $this->wasmerAppId !== '') {
                if ($nodeData["app"]["id"] !== $this->wasmerAppId) {
                    die('Error while doing Magic Login: Database app doesn\'t match.');
                }
            }
            
            // Store the admin URL in a cookie so we can display a "Go to Dashboard" link
            $adminUrl = $nodeData["app"]["adminUrl"];
            setcookie("wasmer_admin_url", $adminUrl, time() + 3600, "/");

            // Build the database server address (host:port format)
            $server = $nodeData["host"];
            if ($nodeData["port"]) {
                $server .= ":" . $nodeData["port"];
            }
            
            // Inject the database credentials into POST data
            // This simulates a manual login form submission, triggering Adminer's authentication
            // The user is automatically logged in without seeing the login form
            $_POST["auth"] = array(
                "driver" => "server",
                "server" => $server,
                "username" => $nodeData["username"],
                "db" => $nodeData["name"],
                "password" => $nodeData["password"],
            );
            $_POST["password"] = $nodeData["password"];
        }
    }

    function name()
    {
        return "Wasmer DB Explorer";
    }

    function head()
    {
        echo '<link rel="stylesheet" href="static/wasmer.css">';
    }

    /**
     * Add custom navigation link to return to Wasmer dashboard
     * 
     * If a magic login was performed, the admin URL is stored in a cookie.
     * This function adds a navigation link at the top of Adminer to easily
     * return to the Wasmer app dashboard.
     */
    function navigation($missing)
    {
        if (isset($_COOKIE["wasmer_admin_url"])) {
            $adminUrl = $_COOKIE["wasmer_admin_url"];
            $escapedAdminUrl = htmlspecialchars($adminUrl);
            if ($escapedAdminUrl) {
?>
                <a id="wasmer-dashboard-link" href="<?php echo $escapedAdminUrl; ?>">
                    <svg viewBox="0 0 29 34" height="1em" width="1em">
                        <g clip-path="url(#prefix__clip0_1268_12249)">
                            <path d="M0 12.3582C0 10.4725 0 9.52973 0.507307 9.23683C1.01461 8.94394 1.83111 9.41534 3.46411 10.3581L10.784 14.5843C12.417 15.5271 13.2335 15.9985 13.7408 16.8771C14.2481 17.7558 14.2481 18.6986 14.2481 20.5843V29.0364C14.2481 30.9221 14.2481 31.8649 13.7408 32.1578C13.2335 32.4507 12.417 31.9793 10.784 31.0365L3.4641 26.8103C1.83111 25.8675 1.01461 25.3961 0.507307 24.5175C0 23.6388 0 22.696 0 20.8103V12.3582Z"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M7.46147 5.14203C6.95416 5.43492 6.95416 6.37773 6.95416 8.26335V9.18177L13.9688 13.2317C15.6018 14.1745 16.4183 14.6459 16.9256 15.5246C17.433 16.4032 17.433 17.346 17.433 19.2317V26.7654L17.7382 26.9416C19.3711 27.8845 20.1876 28.3559 20.695 28.063C21.2023 27.7701 21.2023 26.8273 21.2023 24.9416V16.4894C21.2023 14.6038 21.2023 13.661 20.695 12.7823C20.1876 11.9037 19.3711 11.4323 17.7382 10.4895L10.4183 6.26334C8.78527 5.32054 7.96878 4.84914 7.46147 5.14203Z"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M14.5533 1.05023C14.046 1.34313 14.046 2.28594 14.046 4.17156V5.09003L21.0607 9.13993C22.6937 10.0827 23.5102 10.5541 24.0175 11.4328C24.5248 12.3115 24.5248 13.2543 24.5248 15.1399V22.6736L24.83 22.8499C26.463 23.7927 27.2795 24.2641 27.7868 23.9712C28.2941 23.6783 28.2941 22.7355 28.2941 20.8498V12.3976C28.2941 10.512 28.2941 9.56922 27.7868 8.69054C27.2795 7.81187 26.463 7.34046 24.83 6.39766L17.5101 2.17155C15.8771 1.22874 15.0606 0.757338 14.5533 1.05023Z"></path>
                        </g>
                        <defs>
                            <clipPath id="prefix__clip0_1268_12249">
                                <path fill="#fff" d="M0 0h29v36H0z"></path>
                            </clipPath>
                        </defs>
                    </svg>
                    Go to
                    Wasmer App Dashboard
                </a>

<?php
            }
        }
    }
}
