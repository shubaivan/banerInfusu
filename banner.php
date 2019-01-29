<?php
$mysqli = new DB('test', 'root', '3022666', 'localhost', 'visitors');
$result = $mysqli->connect();
$connection = $mysqli->getCon();

if ($result) {
    foreach (getallheaders() as $name => $value) {
        if ($name == 'User-Agent') {
            $userAgent = $value;
        }
    }

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $url = $_SERVER['HTTP_REFERER'];

    $viewDate = date("Y-m-d H:i:s", time());

    $visitor = new Visitor(
        $ip,
        $userAgent,
        $viewDate,
        $url,
        $connection
    );

    $visitor->visit();

    $image = new Image ('A Simple Text String');
    $image->createImage();
}

class Image
{
    /**
     * @var string
     */
    private $imageName;

    /**
     * Image constructor.
     * @param string $imageName
     */
    public function __construct(string $imageName)
    {
        $this->imageName = $imageName;
    }

    public function createImage()
    {
        // Создаём пустое изображение и добавляем текст
        $im = imagecreatetruecolor(120, 20);
        $text_color = imagecolorallocate($im, 233, 14, 91);
        imagestring($im, 1, 5, 5, $this->getImageName(), $text_color);

        // Устанавливаем тип содержимого в заголовок, в данном случае image/jpeg
        header('Content-Type: image/jpeg');

        // Выводим изображение
        imagejpeg($im);

        // Освобождаем память
        imagedestroy($im);
    }

    /**
     * @return string
     */
    public function getImageName()
    {
        return $this->imageName;
    }
}

class DB
{
    /**
     * @var string
     */
    private $mysqliName;

    /**
     * @var string
     */
    private $mysqliUser;

    /**
     * @var string
     */
    private $mysqliPass;

    /**
     * @var string
     */
    private $mysqliHost;

    /**
     * @var string
     */
    private $table;

    /**
     * DB constructor.
     * @param string $mysqliName
     * @param string $mysqliUser
     * @param string $mysqliPass
     * @param string $mysqliHost
     * @param string $table
     */
    public function __construct(
        $mysqliName,
        $mysqliUser,
        $mysqliPass,
        $mysqliHost,
        $table
    ) {
        $this->dbName = $mysqliName;
        $this->dbUser = $mysqliUser;
        $this->dbPass = $mysqliPass;
        $this->dbHost = $mysqliHost;
        $this->table = $table;
    }

    /**
     * @return bool
     */
    public function connect()
    {
        $connectionnectDb = new mysqli(
            $this->getDbHost(),
            $this->getDbUser(),
            $this->getDbPass(),
            $this->getDbName()
        );
        $this->con = $connectionnectDb;

        if ((mysqli_connect_errno()) OR ((mysqli_num_rows(mysqli_query($connectionnectDb, "SHOW TABLES LIKE '" . $this->table . "'")) != 1))) {
            printf("Connection failed: %s\
                            ", mysqli_connect_error());
            exit();
        }
        return true;

    }

    /**
     * @return string
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * @return string
     */
    public function getDbUser()
    {
        return $this->dbUser;
    }

    /**
     * @return string
     */
    public function getDbPass()
    {
        return $this->dbPass;
    }

    /**
     * @return string
     */
    public function getDbHost()
    {
        return $this->dbHost;
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getCon()
    {
        return $this->con;
    }
}

class Visitor
{
    /**
     * @var string
     */
    protected $ip;

    /**
     * @var string
     */
    protected $userAgent;

    /**
     * @var string
     */
    protected $viewDate;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var int
     */
    protected $count = 0;

    /**
     * @var mysqli
     */
    protected $connection;

    /**
     * Visitor constructor.
     * @param string $ip
     * @param string $userAgent
     * @param string $viewDate
     * @param string $url
     * @param mysqli $connection
     */
    public function __construct(
        $ip,
        $userAgent,
        $viewDate,
        $url,
        mysqli $connection
    )
    {
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->viewDate = $viewDate;
        $this->url = $url;
        $this->connection = $connection;
    }

    /**
     * @return void
     */
    public function visit()
    {
        $result = $this->checkIfVisitorExist($this->ip, $this->userAgent, $this->url);
        if (!$result) {
            $this->createNewVisitor($this->ip, $this->userAgent, $this->url, $this->viewDate);
        } else {
            $id = (int)$result['id'];
            $lastCount = (int)$result['views_count'];

            $this->updateVisitor($id, $lastCount, $this->viewDate);
        }
    }

    private function checkIfVisitorExist($ip, $userAgent, $url)
    {
        $stmt = $this->connection->prepare("
            SELECT * FROM visitors WHERE ip_address= ? AND user_agent= ? AND page_url= ? LIMIT 1
        ");
        $stmt->bind_param("sss", $ip, $userAgent, $url);
        $stmt->execute();
        $getResult = $stmt->get_result();
        $numRows = $getResult->num_rows;

        if ($numRows > 0) {
            $result = $getResult->fetch_array(MYSQLI_ASSOC);
            return $result;
        }

        return false;
    }

    /**
     * @param $ip
     * @param $userAgent
     * @param $url
     * @param $viewDate
     * @return bool
     */
    private function createNewVisitor($ip, $userAgent, $url, $viewDate)
    {
        $stmt = $this->connection->prepare("
            INSERT INTO visitors (ip_address, user_agent, view_date, page_url, views_count) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $viewsCount = 1;
        $stmt->bind_param('ssssd', $ip, $userAgent, $viewDate, $url, $viewsCount);
        $result = $stmt->execute();

        if ($result === TRUE) {
            $stmt->close();
            $this->connection->close();

            return true;
        }

        return false;
    }

    /**
     * @param int $id
     * @param int $lastCount
     * @param string $viewDate
     * @return bool
     */
    private function updateVisitor($id, $lastCount, $viewDate)
    {
        $newCount = $lastCount + 1;

        $stmt = $this->connection->prepare("
            UPDATE visitors SET views_count= ?, view_date= ?  WHERE id= ?
        ");

        $stmt->bind_param('dsd', $newCount, $viewDate, $id);
        $result = $stmt->execute();

        if ($result === TRUE) {
            $stmt->close();
            $this->connection->close();

            return true;
        }

        return false;
    }
}


