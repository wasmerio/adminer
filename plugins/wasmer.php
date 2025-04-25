<?

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

class AdminerWasmer
{
    private $wasmerAppId;

    function __construct()
    {
        $this->wasmerAppId = getenv("WASMER_APP_ID");

        if (isset($_GET["magiclogin"]) && isset($_GET["dbid"]) && !isset($_GET["username"])) {
            $url = getenv("WASMER_GRAPHQL_URL");
            if (!$url) {
                die('Error while doing Magic Login: WASMER_GRAPHQL_URL environment variable is not set.');
            }
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

            $server = $nodeData["host"];
            if ($nodeData["port"]) {
                $server .= ":" . $nodeData["port"];
            }
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

    function navigation($missing)
    {
        if (isset($_COOKIE["wasmer_admin_url"])) {
            $adminUrl = $_COOKIE["wasmer_admin_url"];
            $escapedAdminUrl = htmlspecialchars($adminUrl);
            if ($escapedAdminUrl) {
?>
                <a id="wasmer-dashboard-link" href="<? echo "$escapedAdminUrl"; ?>">
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

<?
            }
        }
    }
}
