<?php
require_once __DIR__ . '/../classes/user_class.php';

function validate_register_ctr($data) {
    $errors = [];

    if (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 80) {
        $errors['name'] = 'Enter a display name of 2 to 80 characters.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (strlen($data['password']) < 8 || strlen($data['password']) > 72) {
        $errors['password'] = 'Use a password of 8 to 72 characters.';
    }
    if ($data['password'] !== $data['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    return $errors;
}

function register_user_ctr($data) {
    $errors = validate_register_ctr($data);
    if ($errors) {
        return $errors;
    }

    $user = new User();
    if ($user->get_user_by_email($data['email'])) {
        return ['email' => 'An account with this email already exists.'];
    }

    try {
        $user->add_user($data['name'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT));
    } catch (mysqli_sql_exception $error) {
        // Two sign-ups with one email at the same moment: the database's UNIQUE rule catches it.
        if ($error->getCode() === 1062) {
            return ['email' => 'An account with this email already exists.'];
        }
        error_log($error->getMessage());
        return ['general' => 'Registration failed. Please try again.'];
    }

    return [];
}

/** Returns the user when the login is correct, otherwise null. */
function login_user_ctr($email, $password) {
    $user = (new User())->get_user_by_email($email);
    if ($user === null) {
        // Hash anyway, so unknown emails take as long as wrong passwords.
        password_hash($password, PASSWORD_DEFAULT);
        return null;
    }
    return password_verify($password, $user['user_pass']) ? $user : null;
}

function get_user_ctr($id) {
    return (new User())->get_user_by_id($id);
}
?>
