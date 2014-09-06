<?php

class Auth extends MethodHandler
{
    /**
     * Register an user
     * @throws Exception
     */
    public function registerHandler() {
        $this->requireParam(array("email", "first_name", "last_name", "gender", "password", "birthday"));

        // check inputs
        $email = $this->params["email"];
        if (!preg_match("/^[^\\s]+@[^\\s]+\\.[^\\s]+$/s", $email))
            throw new Exception("Invalid email format");

        $password = $this->params["password"];
        if (strlen($password) < 6)
            throw new Exception("Password is too short");

        $gender = User::getGenderId($this->params["gender"]);
        $firstName = $this->params["first_name"];
        $lastName = $this->params["last_name"];
        $birthday = Utils::getBirthday($this->params["birthday"]);

        $passwordHash = hash("sha512", $password);

        if (strlen(trim($firstName)) == 0)
            throw new Exception("First name can not be empty");

        $existing = $this->database->fetchColumn("SELECT COUNT(1) FROM users WHERE email = :email", array("email" => $email));
        if ($existing > 0)
            throw new Exception("User with this email already exists");

        // create user
        $this->database->exec("INSERT INTO users (first_name, last_name, email, birthday, password, active, gender, verified, created, privacy)
                                          VALUES (:first_name, :last_name, :email, :birthday, UNHEX(:password), :active, :gender, :verified, :created, 0)",
            array(
                "first_name" => $firstName,
                "last_name" => $lastName,
                "email" => $email,
                "birthday" => $birthday,
                "password" => $passwordHash,
                "active" => "1",
                "gender" => $gender,
                "verified" => "0",
                "created" => time()
            ));

        $userId = $this->database->lastInsertId();

        // update name
        $this->database->exec("UPDATE users SET users.name = CONCAT(users.first_name, ' ', users.last_name) WHERE id = :user_id",
            array("user_id" => $userId));

        // create verification email
        $this->createVerificationEmail($email, $userId);

        // wont work this need logged user
        // $this->logActivity(Activity::ACTIVITY_USER_CREATE, 0, self::OBJECT_TYPE_USER, $userId);

        Activity::create($this->database, $userId, Activity::ACTIVITY_USER_CREATE, 0, self::OBJECT_TYPE_USER, $userId);
        $this->response["success"] = 1;
        $this->output();
    }

    public function loginHandler() {
        $this->requireParam(array("email", "password"));

        $password = $this->params["password"];
        $email = $this->params["email"];
        $user = $this->database->fetch("SELECT id,HEX(password) as password FROM users WHERE email = :email", array("email" => $email));

        if (!$user)
            throw new Exception("No such user");

        $userId = $user["id"];
        $storedPassword = $user["password"];

        if (strncasecmp($storedPassword, hash("sha512", $password), strlen($storedPassword)) !== 0)
            throw new Exception("Invalid password");

        // create a new session
        $this->response["session"] = $this->createSession($userId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function logoutHandler() {
        $this->requireParam(array("session"));

        $this->database->exec("DELETE FROM sessions WHERE session = UNHEX(:session)",
            array("session" => $this->params["session"]));

        $this->response["success"] = 1;
        $this->output();
    }

    public function forgottenPasswordHandler() {
        $this->requireParam("email");

        $email = $this->params["email"];
        $userId = $this->database->fetchColumn("SELECT id FROM users WHERE email = :email", array("email" => $email));

        if (!$userId)
            throw new Exception("No such user");

        $this->createVerificationEmail($email, $userId);

        $this->response["success"] = 1;
        $this->output();
    }

    public function verifyHandler() {
        $this->requireParam(array("hash", "user_id"));

        $hash = $this->params["hash"];
        $userId = $this->params["user_id"];

        $storedHash = $this->database->fetchColumn("SELECT HEX(hash) as hash FROM email_verify WHERE user_id = :user_id", array("user_id" => $userId));

        if (!$storedHash)
            throw new Exception("No awaiting verification");

        if (strncasecmp($hash, $storedHash, strlen($hash)) !== 0)
            throw new Exception("Invalid hash");

        // set as verified
        $this->database->exec("DELETE FROM email_verify WHERE user_id = :user_id",
            array("user_id" => $userId));

        $this->database->exec("UPDATE users SET verified = 1 WHERE id = :user_id",
            array("user_id" => $userId));

        $this->response["session"] = $this->createSession($userId);
        $this->response["success"] = 1;
        $this->output();
    }

    private function createSession($userId) {
        $session = Utils::getRandom(20);

        $this->database->exec("INSERT INTO sessions (user_id, session, created)
            VALUES(:user_id, UNHEX(:session), :created)",
            array(
                "user_id" => $userId,
                "session" => $session,
                "created" => time()
            ));

        // get and update ip address, as well as location
        $ip = $_SERVER["REMOTE_ADDR"];

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];

        $this->database->exec("UPDATE users SET ip = :ip WHERE id = :user_id",
            array("user_id" => $userId, "ip" => $ip));

        // update location
        User::updateLocationByIp($this->database, $userId);

        return $session;
    }

    private function createVerificationEmail($email, $userId) {
        $verificationHash = Utils::getRandom(20);

        $this->database->exec("DELETE FROM email_verify WHERE user_id = :user_id",
            array("user_id" => $userId));

        $this->database->exec("INSERT INTO email_verify (user_id, hash)
                                                  VALUES(:user_id, UNHEX(:hash))",
            array(
                "user_id" => $userId,
                "hash" => $verificationHash
            ));

        // send verification email
        $verifyUrl = "http://groups.iluzia.cz/auth/verify";

        if (isset($this->params["verify_url"]))
            $verifyUrl = $this->params["verify_url"];

        $verificationLink = "{$verifyUrl}?hash={$verificationHash}&user_id={$userId}";
        mail($email, "Please verify the contact email address for your account", "link: {$verificationLink}");

        // TODO comment out
        if ($this->api->config["environment"] == "development")
            $this->response["verification_link"] = $verificationLink;
    }
}