<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';


$jsonFile = 'events.json';


function loadEvents()
{
    global $jsonFile;
    if (file_exists($jsonFile)) {
        $json = file_get_contents($jsonFile);
        return json_decode($json, true);
    }
    return [];
}

function saveEvents($events)
{
    global $jsonFile;
    file_put_contents($jsonFile, json_encode($events, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEvent = [
        'id' => uniqid(),
        'title' => htmlspecialchars($_POST['title']),
        'date' => $_POST['date'],
        'time' => $_POST['time'],
        'description' => htmlspecialchars($_POST['description']),
        'type' => $_POST['type'],
        'email' => $_POST['email']
    ];

    $events = loadEvents();
    array_push($events, $newEvent);
    saveEvents($events);

    $icsContent = "BEGIN:VCALENDAR\n";
    $icsContent .= "VERSION:2.0\n";
    $icsContent .= "BEGIN:VEVENT\n";
    $icsContent .= "SUMMARY:" . $newEvent['title'] . "\n";
    $icsContent .= "DTSTART:" . date('Ymd\THis\Z', strtotime($newEvent['date'] . ' ' . $newEvent['time'])) . "\n";
    $icsContent .= "DTEND:" . date('Ymd\THis\Z', strtotime($newEvent['date'] . ' ' . $newEvent['time'] . ' +1 hour')) . "\n";
    $icsContent .= "DESCRIPTION:" . $newEvent['description'] . "\n";
    $icsContent .= "END:VEVENT\n";
    $icsContent .= "END:VCALENDAR";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        // $mail->Username = 'GMAIL acc';
        // $mail->Password = 'own gmail app pasword';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // $mail->setFrom('GMAIL ACC', 'own name');
        $mail->addAddress($newEvent['email']);

        $mail->isHTML(true);
        $mail->Subject = $newEvent['title'];
        $mail->Body = "Evenement: " . $newEvent['title'] . "<br>Datum: " . $newEvent['date'] . "<br>Tijd: " . $newEvent['time'] . "<br>Beschrijving: " . $newEvent['description'];

        $mail->addStringAttachment($icsContent, 'event.ics', 'base64', 'text/calendar');

        $mail->send();

        header("Location: " . $_SERVER['PHP_SELF'] . "?status=success");
        exit;
    } catch (Exception $e) {
        echo "E-mail kon niet worden verzonden. Foutmelding: {$mail->ErrorInfo}";
    }
}

if (isset($_GET['delete'])) {
    $events = loadEvents();
    $events = array_filter($events, function ($event) {
        return $event['id'] !== $_GET['delete'];
    });
    saveEvents($events);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$events = loadEvents();
?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container py-5">
        <h1 class="text-center mb-5">Agenda</h1>

        <div class="card shadow mb-5">
            <div class="card-body">
                <h2 class="mb-4">Voeg evenement toe</h2>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Titel</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Datum</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tijd</label>
                            <input type="time" name="time" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Omschrijving</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="appointment">Afspraak</option>
                                <option value="task">Taak</option>
                                <option value="reminder">Herinnering</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Toevoegen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <h2 class="mb-4">Evenementen</h2>
        <?php if (empty($events)): ?>
            <div class="alert alert-info">Geen evenementen gevonden</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($events as $event): ?>
                    <div class="col">
                        <div class="card event-card shadow-sm h-100">
                            <div class="card-body event-type <?= 'type-' . $event['type'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title"><?= htmlspecialchars($event['title']) ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            <?= date('d-m-Y', strtotime($event['date'])) ?>
                                            <?= $event['time'] ? ' | ' . date('H:i', strtotime($event['time'])) : '' ?>
                                            | <?= ucfirst($event['type']) ?>
                                        </h6>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                    </div>
                                    <a href="?delete=<?= $event['id'] ?>" class="btn btn-danger btn-sm" blac>×</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>