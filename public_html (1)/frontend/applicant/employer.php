<?php
// php/employer.php
$flask_upload = 'http://127.0.0.1:5000/upload_job';
$flask_jobs = 'http://127.0.0.1:5000/jobs';
$flask_job_matches_base = 'http://127.0.0.1:5000/job_matches/';

$response = null;
$ranked = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // either job_text field or file upload 'job'
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $data = array('title' => $title);
    if (!empty($_POST['job_text'])) {
        $data['job_text'] = $_POST['job_text'];
    } elseif (isset($_FILES['job'])) {
        $tmp = $_FILES['job']['tmp_name'];
        $name = $_FILES['job']['name'];
        $cfile = new CURLFile($tmp, 'text/plain', $name);
        $data = array('job' => $cfile, 'title' => $title);
    } else {
        // nothing
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $flask_upload);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    if (isset($json['job_id'])) {
        // fetch ranked matches
        $url = $flask_job_matches_base . urlencode($json['job_id']);
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        $mres = curl_exec($ch2);
        curl_close($ch2);
        $ranked = json_decode($mres, true);
    }
}

// fetch jobs for display
$jobs_list = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $flask_jobs);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$jres = curl_exec($ch);
curl_close($ch);
$jobs_list = json_decode($jres, true);
?>
<!doctype html>
<html>
<head><meta charset="utf-8"/><title>Employer</title></head>
<body>
  <h1>Employer Page</h1>
  <p><a href="loginregister">Home</a></p>

  <h2>Create Job Posting</h2>
  <form method="post" enctype="multipart/form-data">
    <label>Job title: <input type="text" name="title" /></label><br/><br/>
    <textarea name="job_text" rows="8" cols="80" placeholder="Paste job description (skills preferred)"></textarea><br/>
    <p>Or upload a .txt file: <input type="file" name="job" accept=".txt" /></p>
    <button type="submit">Upload Job</button>
  </form>

  <?php if ($response): ?>
    <h3>API Response</h3>
    <pre><?php echo htmlspecialchars($response); ?></pre>
  <?php endif; ?>

  <?php if (!empty($ranked)): ?>
    <h3>Ranking of Applicants (best -> worst)</h3>
    <table border="1" cellpadding="6">
      <tr><th>Rank</th><th>Applicant</th><th>Resume ID</th><th>Score (%)</th></tr>
      <?php $r=1; foreach ($ranked as $row): ?>
        <tr>
          <td><?php echo $r++; ?></td>
          <td><?php echo htmlspecialchars($row['applicant_name']); ?></td>
          <td><?php echo htmlspecialchars($row['resume_id']); ?></td>
          <td><?php echo htmlspecialchars($row['score']); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <h2>All Jobs</h2>
  <?php if ($jobs_list): ?>
    <ul>
      <?php foreach ($jobs_list as $jid => $job): ?>
        <li>
          <strong><?php echo htmlspecialchars($job['title'] ?: $jid); ?></strong>
          — <a href="<?php echo htmlspecialchars('job_matches.php?job_id='.$jid); ?>">View ranking</a>
          <pre><?php echo htmlspecialchars(substr($job['job_text'],0,200)); ?></pre>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No jobs posted yet.</p>
  <?php endif; ?>
</body>
</html>
127.0.0.1