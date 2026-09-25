<?php
$flask_upload = 'http://127.0.0.1:5000/upload_resume';
$flask_jobs = 'http://127.0.0.1:5000/jobs';
$flask_resume_matches_base = 'http://127.0.0.1:5000/resume_matches/';

$response = null;
$matches = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resume'])) {
    $tmp = $_FILES['resume']['tmp_name'];
    $name = $_FILES['resume']['name'];
    $applicant_name = $_POST['applicant_name'] ?? 'Applicant';

    $cfile = new CURLFile($tmp, 'application/pdf', $name);
    $post = array('resume' => $cfile, 'applicant_name' => $applicant_name);

    $ch = curl_init($flask_upload);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    if (isset($json['resume_id'])) {
        $rid = $json['resume_id'];
        $url = $flask_resume_matches_base . urlencode($rid);

        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $mres = curl_exec($ch2);
        curl_close($ch2);

        $matches = json_decode($mres, true);
    }
}

$jobs_list = [];
$ch = curl_init($flask_jobs);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$jres = curl_exec($ch);
curl_close($ch);
$jobs_list = json_decode($jres, true);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Job Seeker</title>

    <style>
        body { font-family: Arial; margin: 20px; }

        #chatbot-box {
            width: 420px;
            border: 2px solid #444;
            padding: 15px;
            border-radius: 10px;
            background: #f8f8f8;
            margin-bottom: 25px;
        }
        .bot-question {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .option-btn {
            padding: 10px;
            margin: 5px 0;
            width: 100%;
            background: #ddd;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .option-btn:hover { background: #ccc; }

        #chatbot-result {
            margin-top: 15px;
            padding: 10px;
            background: #e3ffe3;
            border: 1px solid #7ac97a;
            display: none;
            font-size: 18px;
        }
    </style>
</head>

<body>

<h1>Job Seeker Page</h1>
<p><a href="loginregister">Home</a></p>

<h2>Career Assessment Chatbot</h2>

<div id="chatbot-box">
    <div id="question-container"></div>
    <div id="chatbot-result"></div>
</div>

<script>
const questions = [
    { q: "What is your highest level of education?",
      options: ["High School","Associate","Bachelor","Master","PhD"],
      values: [10,15,25,35,45] },

    { q: "How many years of professional experience do you have in your field?",
      options: ["0-1 years","2-3 years","4-5 years","6+ years"],
      values: [10,20,30,40] },

    { q: "How would you rate your relevant skills for your field?",
      options: ["Beginner","Intermediate","Advanced","Expert"],
      values: [10,20,30,40] },

    { q: "Do you have any professional certifications or licenses?",
      options: ["No","1-2 certifications","3-4 certifications","5+ certifications"],
      values: [0,15,25,35] }
];

let current = 0;
let answers = [];

function loadQuestion() {
    const qc = document.getElementById("question-container");
    const q = questions[current];

    let html = '<div class=\"bot-question\">' + q.q + '</div>';
    q.options.forEach((opt, i) => {
        html += '<button class=\"option-btn\" onclick=\"selectOption(' + i + ')\">' + opt + '</button>';
    });

    qc.innerHTML = html;
}

function selectOption(i) {
    answers.push({ question: questions[current].q, option_index: i });

    current++;
    if (current >= questions.length) {
        finishChat();
    } else {
        loadQuestion();
    }
}

function finishChat() {
    document.getElementById("question-container").innerHTML =
        "<p>Calculating your employability score...</p>";

    fetch("http://127.0.0.1:5000/chatbot_score", {
        method: "POST",
        headers: { "Content-Type": "application/json"},
        body: JSON.stringify({ answers: answers })
    })
    .then(res => res.json())
    .then(data => {
        const box = document.getElementById("chatbot-result");
        box.style.display = "block";
        box.innerHTML = '<strong>Your Chatbot Score:</strong> ' + data.total_score + '%';
    });
}

loadQuestion();
</script>
<h2>Upload Resume (PDF)</h2>
<form method="post" enctype="multipart/form-data">
    <label>Your name: <input type="text" name="applicant_name" required></label><br><br>
    <input type="file" name="resume" accept="application/pdf" required>
    <button type="submit">Upload Resume</button>
</form>

<?php if ($response): ?>
    <h3>Resume Upload Response</h3>
    <pre><?php echo htmlspecialchars($response); ?></pre>
<?php endif; ?>

<?php if (!empty($matches)): ?>
<h3>Your Scores for Jobs</h3>
<table border="1" cellpadding="6">
    <tr><th>Job ID</th><th>Job Title</th><th>Score (%)</th></tr>
    <?php foreach ($matches as $m): ?>
    <tr>
        <td><?php echo htmlspecialchars($m['job_id']); ?></td>
        <td><?php echo htmlspecialchars($m['job_id']); ?></td>
        <td><?php echo htmlspecialchars($m['score']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Available Jobs</h2>
<?php if ($jobs_list): ?>
<ul>
<?php foreach ($jobs_list as $jid => $job): ?>
    <li><strong><?php echo htmlspecialchars($job['title'] ?: $jid); ?></strong>
        — <em><?php echo nl2br(htmlspecialchars(substr($job['job_text'],0,180))); ?></em>
    </li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p>No jobs uploaded yet.</p>
<?php endif; ?>

</body>
</html>