<?php
require_once __DIR__ . '/../core/db_class.php';

class User extends Database {
    public function add_user($name, $email, $password_hash) {
        return $this->run('INSERT INTO users (user_name, user_email, user_pass) VALUES (?, ?, ?)', 'sss', $name, $email, $password_hash);
    }

    public function get_user_by_email($email) {
        return $this->fetch_one('SELECT * FROM users WHERE user_email = ?', 's', $email);
    }

    public function get_user_by_id($id) {
        return $this->fetch_one('SELECT * FROM users WHERE user_id = ?', 'i', $id);
    }
}
?>
