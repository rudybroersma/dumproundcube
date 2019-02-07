<?php
include("/usr/share/psa-roundcube/config/config.inc.php");

#$config['db_dsnw'] = 'mysql://roundcube:X@localhost/roundcubemail';

$db_array = explode("/", $config['db_dsnw']);
$login_array = explode(":", $db_array[2]);
$password_array = explode("@", $login_array[1]);

$database = array();
$database['database'] = $db_array[3];
$database['username'] = $login_array[0];
$database['password'] = $password_array[0];
$database['hostname'] = $password_array[1];

$dsn = "mysql:host=" . $database['hostname'] . ";dbname=" . $database['database'] . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $pdo = new PDO($dsn, $database['username'], $database['password'], $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

$options = getopt("a:");
$address = trim($options['a']);

echo "USE roundcubemail;\n";
echo "DELETE FROM contactgroupmembers WHERE contact_id IN (SELECT contact_id FROM contacts WHERE user_id = (SELECT user_id FROM users WHERE username = \"$address\"));\n";
echo "DELETE FROM contacts WHERE user_id IN (SELECT user_id FROM users WHERE username = \"$address\");\n";
echo "DELETE FROM contactgroups WHERE user_id IN (SELECT user_id FROM users WHERE username = \"$address\");\n";
echo "DELETE FROM identities WHERE user_id = (SELECT user_id FROM users WHERE username = \"$address\");\n";
echo "DELETE FROM users WHERE username = \"$address\";\n";

# contactgroups (user_id, contactgroup_id)
# contactgroupmembers (contactgroup_id, contact_id)

# user_id | username | mail_host | created | last_login | language | preferences | failed_login | failed_login_counter

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute([ $address ]);
while ($row = $stmt->fetch())
{
  echo  "INSERT INTO users VALUES(NULL, " .
                                $pdo->quote($row['username'])    . ", " .
                                $pdo->quote($row['mail_host'])   . ", " .
                                $pdo->quote($row['created'])     . ", " .
                                $pdo->quote($row['last_login'])  . ", " .
                                $pdo->quote($row['language'])    . ", " .
                                $pdo->quote($row['preferences']) . ", " .
                                $pdo->quote($row['failed_login']) . ", '0');\n";
}

# contactgroupmembers (contactgroup_id, contact_id)

$stmt = $pdo->prepare('SELECT * FROM identities WHERE user_id = (SELECT user_id FROM users WHERE username = ?)');
$stmt->execute([ $address ]);
while ($row = $stmt->fetch())
{
  echo "INSERT INTO identities VALUES(NULL, (SELECT user_id FROM users WHERE username = \"$address\"), " .
       $pdo->quote($row['changed'] )       . ", " .
       $row['del']                         . ", " .
       $pdo->quote($row['standard'])       . ", " .
       $pdo->quote($row['name'])           . ", " .
       $pdo->quote($row['organization'])   . ", " .
       $pdo->quote($row['email'])          . ", " .
       $pdo->quote($row['reply-to'])       . ", " .
       $pdo->quote($row['bcc'])            . ", " .
       $pdo->quote($row['signature'])      . ", " .
       $pdo->quote($row['html_signature']) . ");\n";
}

$stmt = $pdo->prepare('SELECT * FROM contacts WHERE user_id = (SELECT user_id FROM users WHERE username = ?)');
$stmt->execute([ $address ]);
while ($row = $stmt->fetch())
{
  echo "INSERT INTO contacts VALUES(NULL, " .
       $pdo->quote($row['changed'] )       . ", " .
       $row['del']                         . ", " .
       $pdo->quote($row['name'])           . ", " .
       $pdo->quote($row['email'])          . ", " .
       $pdo->quote($row['firstname'])      . ", " .
       $pdo->quote($row['surname'])        . ", " .
       $pdo->quote($row['vcard'])         . ", " .
       $pdo->quote($row['words'])          . ", " .
       "(SELECT user_id FROM users WHERE username = \"$address\"));\n";
}

$stmt = $pdo->prepare('SELECT * FROM contactgroups WHERE user_id = (SELECT user_id FROM users WHERE username = ?)');
$stmt->execute([ $address ]);
while ($row = $stmt->fetch())
{
  echo "INSERT INTO contactgroups VALUES(NULL, " .
       "SELECT user_id FROM users WHERE username = \"$address\"), " .
       $pdo->quote($row['changed'] )       . ", " .
       $row['del']                         . ", " .
       $pdo->quote($row['name'])           . ");\n";
}

$stmt = $pdo->prepare('SELECT contactgroups.user_id,
                              contactgroupmembers.contact_id,
                              contactgroups.name,
                              contacts.email
                       FROM contactgroups,
                            contacts,
                            contactgroupmembers
                       WHERE contactgroups.contactgroup_id = contactgroupmembers.contactgroup_id AND
                             contacts.contact_id = contactgroupmembers.contact_id AND
                             contactgroupmembers.contactgroup_id
                       IN (SELECT contactgroup_id FROM contactgroups WHERE user_id = (
                           SELECT user_id FROM users WHERE username = ?)
                          )');

$stmt->execute([ $address ]);
while ($row = $stmt->fetch())
{
  echo "INSERT INTO contactgroupmembers VALUES(" .
       "(SELECT contactgroup_id FROM contactgroups WHERE name = " .
       $pdo->quote($row['name']) . " AND user_id = (SELECT user_id FROM users WHERE username = \"" . $address . "\"))," .
       "(SELECT contact_id FROM contacts WHERE email = " .
       $pdo->quote($row['email']) . " AND user_id = (SELECT user_id FROM users WHERE username = \"" . $address . "\"))," .
       $pdo->quote($row['created']) . ");\n";
}
