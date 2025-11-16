<?php
$pageTitle = 'Health News - Admin';
include 'includes/header.php';

$success = '';
$error = '';

// Add News
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_news'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $author = sanitize($_POST['author']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Handle image upload
    $image = 'news-default.jpg';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_result = uploadImage($_FILES['image'], 'news');
        if ($upload_result['success']) {
            $image = $upload_result['filename'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO health_news (title, description, image, author, is_published, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $image, $author, $is_published, $_SESSION['user_id']]);
        
        logActivity($pdo, $_SESSION['user_id'], 'NEWS_CREATED', 'health_news', $pdo->lastInsertId(), "News: $title");
        
        $success = 'News article published successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to publish news';
    }
}

// Update News
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_news'])) {
    $news_id = (int)$_POST['news_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $author = sanitize($_POST['author']);
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Handle image upload
    $image = $_POST['current_image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_result = uploadImage($_FILES['image'], 'news');
        if ($upload_result['success']) {
            $image = $upload_result['filename'];
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE health_news 
            SET title = ?, description = ?, image = ?, author = ?, is_published = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $image, $author, $is_published, $news_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'NEWS_UPDATED', 'health_news', $news_id, "News updated: $title");
        
        $success = 'News article updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update news';
    }
}

// Delete News
if (isset($_GET['delete'])) {
    $news_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM health_news WHERE id = ?");
        $stmt->execute([$news_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'NEWS_DELETED', 'health_news', $news_id, 'News deleted');
        
        $success = 'News article deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to delete news';
    }
}

// Toggle Publish Status
if (isset($_GET['toggle'])) {
    $news_id = (int)$_GET['toggle'];
    
    try {
        $stmt = $pdo->prepare("UPDATE health_news SET is_published = NOT is_published WHERE id = ?");
        $stmt->execute([$news_id]);
        
        $success = 'News status updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update status';
    }
}

// Get all news
$stmt = $pdo->query("
    SELECT hn.*, u.name as creator_name
    FROM health_news hn
    LEFT JOIN users u ON hn.created_by = u.id
    ORDER BY hn.created_at DESC
");
$news = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8" x-data="{ showAddModal: false, editNews: null }">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Health News & Insights</h1>
        <button @click="showAddModal = true" 
                class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700">
            ➕ Publish New Article
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- News Grid -->
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($news as $article): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="relative">
                    <img src="/quickmed/assets/images/uploads/news/<?= $article['image'] ?>" 
                         alt="<?= htmlspecialchars($article['title']) ?>"
                         class="w-full h-48 object-cover"
                         onerror="this.src='https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=400&h=300&fit=crop'">
                    
                    <div class="absolute top-3 right-3">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            <?= $article['is_published'] ? 'bg-green-500 text-white' : 'bg-gray-500 text-white' ?>">
                            <?= $article['is_published'] ? '✓ Published' : '○ Draft' ?>
                        </span>
                    </div>
                </div>

                <div class="p-5">
                    <div class="flex items-center gap-2 mb-3 text-xs text-gray-500">
                        <span>📅 <?= date('M d, Y', strtotime($article['created_at'])) ?></span>
                        <?php if ($article['author']): ?>
                            <span>• ✍️ <?= htmlspecialchars($article['author']) ?></span>
                        <?php endif; ?>
                    </div>

                    <h3 class="font-bold text-lg mb-2 line-clamp-2"><?= htmlspecialchars($article['title']) ?></h3>
                    <p class="text-sm text-gray-600 mb-4 line-clamp-3"><?= htmlspecialchars($article['description']) ?></p>

                    <?php if ($article['creator_name']): ?>
                        <p class="text-xs text-gray-500 mb-4">Created by: <?= htmlspecialchars($article['creator_name']) ?></p>
                    <?php endif; ?>

                    <div class="flex gap-2">
                        <button @click="editNews = <?= htmlspecialchars(json_encode($article)) ?>"
                                class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700">
                            Edit
                        </button>
                        <a href="?toggle=<?= $article['id'] ?>"
                           class="flex-1 bg-orange-600 text-white py-2 rounded-lg text-sm text-center hover:bg-orange-700">
                            <?= $article['is_published'] ? 'Unpublish' : 'Publish' ?>
                        </a>
                        <a href="?delete=<?= $article['id'] ?>"
                           onclick="return confirm('Delete this article?')"
                           class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                            🗑️
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (count($news) === 0): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <div class="text-6xl mb-4">📰</div>
            <p class="text-gray-600 text-xl mb-6">No news articles yet</p>
            <button @click="showAddModal = true" 
                    class="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-red-700">
                Publish Your First Article
            </button>
        </div>
    <?php endif; ?>

    <!-- Add News Modal -->
    <div x-show="showAddModal" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full my-8">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Publish New Article</h2>
                    <button @click="showAddModal = false" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Article Title *</label>
                        <input type="text" name="title" required
                               placeholder="Enter a compelling title..."
                               class="input-modern">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Description *</label>
                        <textarea name="description" required rows="6"
                                  placeholder="Write the full article content..."
                                  class="input-modern"></textarea>
                    </div>

                    <div class="grid md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Author Name</label>
                            <input type="text" name="author"
                                   placeholder="Dr. John Doe"
                                   class="input-modern">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Featured Image</label>
                            <input type="file" name="image" accept="image/*"
                                   class="input-modern">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <input type="checkbox" name="is_published" id="is_published" checked
                               class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="is_published" class="text-sm font-medium">
                            Publish immediately (uncheck to save as draft)
                        </label>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="add_news"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            📰 Publish Article
                        </button>
                        <button type="button" @click="showAddModal = false"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit News Modal -->
    <div x-show="editNews" 
         x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full my-8">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Edit Article</h2>
                    <button @click="editNews = null" class="text-gray-500 hover:text-gray-700 text-2xl">×</button>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="news_id" x-model="editNews?.id">
                    <input type="hidden" name="current_image" x-model="editNews?.image">
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Article Title *</label>
                        <input type="text" name="title" x-model="editNews.title" required
                               class="input-modern">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold mb-2">Description *</label>
                        <textarea name="description" x-model="editNews.description" required rows="6"
                                  class="input-modern"></textarea>
                    </div>

                    <div class="grid md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Author Name</label>
                            <input type="text" name="author" x-model="editNews.author"
                                   class="input-modern">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Change Image</label>
                            <input type="file" name="image" accept="image/*"
                                   class="input-modern">
                            <p class="text-xs text-gray-500 mt-1">Leave empty to keep current image</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <input type="checkbox" name="is_published" id="edit_is_published"
                               x-bind:checked="editNews?.is_published == 1"
                               class="w-5 h-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                        <label for="edit_is_published" class="text-sm font-medium">
                            Published (visible on homepage)
                        </label>
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="submit" name="update_news"
                                class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700">
                            Update Article
                        </button>
                        <button type="button" @click="editNews = null"
                                class="flex-1 border border-gray-300 py-3 rounded-lg font-semibold hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>