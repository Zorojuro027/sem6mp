<?php
// Define file path to store posts
$postsFile = __DIR__ . '/posts.json';

// Initialize posts file if it doesn't exist
if (!file_exists($postsFile)) {
    file_put_contents($postsFile, json_encode([]));
}

// Load existing posts
$postsContent = file_get_contents($postsFile);
if ($postsContent === false) {
    die('Error reading posts file.');
}
$posts = json_decode($postsContent, true);
if ($posts === null && json_last_error() !== JSON_ERROR_NONE) {
    die('Error decoding JSON data.');
}

// Save new post
$result = file_put_contents($postsFile, json_encode($posts, JSON_PRETTY_PRINT));
if ($result === false) {
    die('Error writing to posts file.');
}


// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $author = isset($_POST['author']) && trim($_POST['author']) !== '' ? trim($_POST['author']) : 'Anonymous';
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if ($title !== '' && $content !== '') {
        $newPost = [
            'author'  => $author,
            'title'   => $title,
            'content' => $content,
            'date'    => date("Y-m-d H:i:s")
        ];
        $posts[] = $newPost;
        file_put_contents($postsFile, json_encode($posts));
        header("Location: comm.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FitSync Community Forum</title>
  <style>
    /* Global Styles */
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
      color: #333;
      padding-left: 220px;
    }
    /* Sidebar placeholder – dynamically loaded sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 200px;
      height: 100vh;
      background-color: hsl(141, 73%, 42%);
      padding: 20px;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
      color: white;
    }
    /* Dark Mode Toggle Button */
    .dark-mode-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      background: #333;
      color: white;
      padding: 8px 12px;
      border-radius: 5px;
      cursor: pointer;
    }
    /* Community forum container */
    .forum-container {
      max-width: 900px;
      margin: 20px auto;
      padding: 20px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h1, h2 {
      text-align: center;
    }
    /* New post form */
    .new-post-form {
      margin-bottom: 40px;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      background: #fafafa;
    }
    .new-post-form input[type="text"],
    .new-post-form textarea {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    .new-post-form button {
      background: #4CAF50;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
    }
    .new-post-form button:hover {
      background: #45a049;
    }
    /* Post card styling */
    .post-card {
      border-bottom: 1px solid #ddd;
      padding: 15px 0;
    }
    .post-card:last-child {
      border-bottom: none;
    }
    .post-card h3 {
      margin: 5px 0;
    }
    .post-meta {
      font-size: 12px;
      color: #777;
    }
    .post-content {
      margin: 10px 0;
    }
    /* Dark Mode styles */
    body.dark-mode {
      background-color: #121212;
      color: #e0e0e0;
    }
    body.dark-mode .forum-container {
      background: #1e1e1e;
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
    }
    body.dark-mode .new-post-form {
      background: #2a2a2a;
      border-color: #444;
    }
    body.dark-mode input[type="text"],
    body.dark-mode textarea {
      background: #333;
      color: #fff;
      border: 1px solid #555;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>FitSync</h2>
    <ul>
      <li><a href="/Home.html" style="color: white; text-decoration: none;">Dashboard</a></li>
      <li><a href="/BMI.html" style="color: white; text-decoration: none;">BMI Calculator</a></li>
      <li><a href="/Diet/diet.html" style="color: white; text-decoration: none;">Diet Gallery</a></li>
      <li><a href="/goals.html" style="color: white; text-decoration: none;">Goals</a></li>
      <li><a href="comm.php" style="color: white; text-decoration: none;">Community Forum</a></li>
    </ul>
  </div>

  <!-- Dark Mode Toggle -->
  <input type="checkbox" id="dark-mode-toggle" style="display:none;">
  <label for="dark-mode-toggle" class="dark-mode-btn">🌙 Dark Mode</label>

  <div class="forum-container">
    <h1>FitSync Community Forum</h1>
    
    <!-- New Post Form -->
    <div class="new-post-form">
      <h2>Create a New Post</h2>
      <form method="POST" action="comm.php">
        <input type="text" name="author" placeholder="Your Name (Optional)" />
        <input type="text" name="title" placeholder="Post Title" required />
        <textarea name="content" rows="5" placeholder="Share your thoughts..." required></textarea>
        <button type="submit">Submit Post</button>
      </form>
    </div>
    
    <!-- Posts List -->
    <?php if (!empty($posts)): ?>
      <?php foreach (array_reverse($posts) as $post): ?>
        <div class="post-card">
          <h3><?php echo htmlspecialchars($post['title']); ?></h3>
          <div class="post-meta">By <?php echo htmlspecialchars($post['author']); ?> on <?php echo htmlspecialchars($post['date']); ?></div>
          <div class="post-content"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No posts yet. Be the first to share with the community!</p>
    <?php endif; ?>
  </div>

  <script>
    // Dark Mode Toggle Logic
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    if (localStorage.getItem('darkMode') === 'enabled') {
      document.body.classList.add('dark-mode');
      darkModeToggle.checked = true;
    }
    document.querySelector('.dark-mode-btn').addEventListener('click', () => {
      document.body.classList.toggle('dark-mode');
      localStorage.setItem('darkMode', document.body.classList.contains('dark-mode') ? 'enabled' : 'disabled');
    });
  </script>

<script>
    (function() {
      // Function to set a cookie
      function setCookie(name, value, days) {
          let expires = "";
          if (days) {
              let date = new Date();
              date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
              expires = "; expires=" + date.toUTCString();
          }
          document.cookie = name + "=" + value + expires + "; path=/";
      }
      
      // Function to get a cookie value
      function getCookie(name) {
          let nameEQ = name + "=";
          let cookiesArray = document.cookie.split(';');
          for (let i = 0; i < cookiesArray.length; i++) {
              let c = cookiesArray[i].trim();
              if (c.indexOf(nameEQ) === 0) {
                  return c.substring(nameEQ.length, c.length);
              }
          }
          return null;
      }
      
      // Function to apply dark mode based on cookie
      function applyDarkMode() {
          let darkModeEnabled = getCookie("darkMode");
          if (darkModeEnabled === "true") {
              document.body.classList.add("dark-mode");
              var toggle = document.getElementById("dark-mode-toggle");
              if (toggle) toggle.checked = true;
          }
      }
      
      // Function to apply meal preference (if element exists)
      function applyMealPreference() {
          let mealPref = getCookie("mealPref");
          let mealPreferenceSelect = document.getElementById("mealPreference");
          if (mealPref && mealPreferenceSelect) {
              mealPreferenceSelect.value = mealPref;
          }
      }
      
      // Set up dark mode toggle event
      var darkModeToggle = document.getElementById("dark-mode-toggle");
      if (darkModeToggle) {
          darkModeToggle.addEventListener("change", function() {
              if (this.checked) {
                  document.body.classList.add("dark-mode");
                  setCookie("darkMode", "true", 30);
              } else {
                  document.body.classList.remove("dark-mode");
                  setCookie("darkMode", "false", 30);
              }
          });
      }
      
      // Set up meal preference change event (if element exists)
      var mealPreferenceSelect = document.getElementById("mealPreference");
      if (mealPreferenceSelect) {
          mealPreferenceSelect.addEventListener("change", function() {
              setCookie("mealPref", this.value, 30);
          });
      }
      
      // Apply preferences on page load
      window.addEventListener("load", function() {
          applyDarkMode();
          applyMealPreference();
      });
    })();
  </script>
</body>
</html>