<?

function wasmer_graphql_query($registry, $query, $variables, $authToken = NULL) {
    // Prepare the payload
    $payload = json_encode([
        "query" => $query,
        "variables" => $variables
    ]);
    $authHeader = $authToken ? "Authorization: Bearer $authToken\r\n": NULL;
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

class AdminerWasmer
{
    private $wasmerAppId;

    function __construct()
    {
        $this->wasmerAppId = getenv("WASMER_APP_ID");

        if (isset($_GET["magiclogin"]) && isset($_GET["dbid"]) && !isset($_GET["username"])) {
            $url = getenv("WASMER_GRAPHQL_URL");
            $authToken = $_GET["magiclogin"];
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
            $responseData = wasmer_graphql_query($url, $query, $variables, $authToken);
            if (!$responseData) {
                die('Error while doing Magic Login: Error occurred while fetching the database credentials.');
            }

            // Extract node data
            $nodeData = isset($responseData['data']['node']) ? $responseData['data']['node'] : null;

            // Check if node data exists
            if ($nodeData === null) {
                die('Error while doing Magic Login: Database with the provided id couldn\'t be found, or you don\'t have access to it.');
            }

            if ($this->wasmerAppId && $this->wasmerAppId !== '') {
                if ($nodeData["app"]["id"] !== $this->wasmerAppId) {
                    die('Error while doing Magic Login: Database app doesn\'t match.');
                }
            }
            $adminUrl = $nodeData["app"]["adminUrl"];
            setcookie("wasmer_admin_url", $adminUrl, time() + 3600, "/");

            $_POST["auth"] = array(
                "driver" => "server",
                "server" => $nodeData["host"],
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

    function navigation($missing)
    {
        if (isset($_COOKIE["wasmer_admin_url"])) {
            $adminUrl = $_COOKIE["wasmer_admin_url"];
            $escapedAdminUrl = htmlspecialchars($adminUrl);
            if ($escapedAdminUrl) {
?>
            <a id="wasmer-dashboard-link" href="<? echo "$escapedAdminUrl"; ?>">
                <svg width="1em" height="1em" viewBox="0 0 29 36" xmlns="http://www.w3.org/2000/svg">
                    <g clip-path="url(#prefix__clip0_1268_12249)">
                        <path d="M8.908 13.95v.1c0 1.187-.746 1.704-1.662 1.157-.917-.546-1.662-1.952-1.662-3.138v-.1L0 8.636v18.719l14.5 8.645V17.28L8.908 13.95z"></path>
                        <path d="M16.158 9.629v.101c0 1.186-.746 1.704-1.662 1.157-.917-.547-1.662-1.952-1.662-3.138v-.101L7.25 4.32v6.697l8.88 5.296v12.023l5.62 3.352V12.97l-5.592-3.34z"></path>
                        <path d="M23.408 5.313v.101c0 1.187-.746 1.704-1.662 1.157-.916-.547-1.662-1.952-1.662-3.138v-.1L14.5 0v6.697l8.88 5.296v12.023L29 27.369V8.649l-5.592-3.336z"></path>
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

<?
        }
    }
    }
}
