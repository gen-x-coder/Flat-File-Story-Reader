<?php

// NOTE:
// Markdown content is trusted (single author).
// No sanitization is applied by design.
// Never use this code in a multi-user enviroment where users can add markdown files.

class StoryManager {
    private $contentDir = 'content';
    
    public function getStories() {
        $stories = [];
        if (!is_dir($this->contentDir) || !is_readable($this->contentDir)) return $stories;
        
        $files = glob($this->contentDir . '/*.md');
        if ($files === false) return $stories;
        
        $currentTime = time();
        
        foreach ($files as $file) {
            if (!is_readable($file)) continue;
            
            $content = file_get_contents($file);
            if ($content === false) continue;
            
            $story = $this->parseMarkdown($content, basename($file));
            if ($story) {
                if ($story['date'] === null || strtotime($story['date']) <= $currentTime) {
                    $stories[] = $story;
                }
            }
        }
        
        usort($stories, function($a, $b) {
            if (isset($a['date']) && isset($b['date']) && $a['date'] !== null && $b['date'] !== null) {
                return strtotime($b['date']) - strtotime($a['date']);
            }
            if (isset($a['date']) && $a['date'] !== null) return -1;
            if (isset($b['date']) && $b['date'] !== null) return 1;
            return strcmp($a['title'], $b['title']);
        });
        
        return $stories;
    }
    
    private function parseMarkdown($content, $filename) {
        $parts = explode('---', $content, 3);
        $title = ucwords(str_replace(['.md', '-'], ['', ' '], $filename));
        $body = $content;
        $date = null;
        $published = false;
        $publishedEpisodes = [];
        
        if (count($parts) >= 3) {
            $frontmatter = $parts[1];
            if (preg_match('/title:\s*(.+)/m', $frontmatter, $matches)) {
                $title = trim($matches[1]);
            }
            if (preg_match('/date:\s*(.+)/m', $frontmatter, $matches)) {
                $date = trim($matches[1]);
            }
            if (preg_match('/published:\s*(.+)/m', $frontmatter, $matches)) {
                $published = strtolower(trim($matches[1])) === 'yes';
            }
            if (preg_match('/published_episodes:\s*\[([^\]]+)\]/m', $frontmatter, $matches)) {
                $episodeList = trim($matches[1]);
                $publishedEpisodes = array_map('intval', array_map('trim', explode(',', $episodeList)));
            }
            $body = trim($parts[2]);
        }
        
        $episodes = $this->detectEpisodes($body);
        $firstEpisodeContent = $episodes['content'][0] ?? $body;
        $excerpt = $this->getExcerpt($firstEpisodeContent);
        
        $isPublished = $published || !empty($publishedEpisodes);
        
        return [
            'filename' => $filename,
            'title' => $title,
            'body' => $body,
            'excerpt' => $excerpt,
            'date' => $date,
            'published' => $isPublished,
            'published_episodes' => $publishedEpisodes,
            'episodes' => $episodes
        ];
    }
    
    private function detectEpisodes($content) {
        preg_match_all('/^#\s+(.+)$/m', $content, $matches, PREG_OFFSET_CAPTURE);
        
        $episodes = ['count' => 1, 'titles' => [], 'content' => []];
        
        if (empty($matches[0])) {
            $episodes['content'][] = $content;
            return $episodes;
        }
        
        $validEpisodes = $matches[0];
        $validTitles = array_map(function($match) { return trim($match[0]); }, $matches[1]);
        
        if (empty($validEpisodes)) {
            $episodes['content'][] = $content;
            return $episodes;
        }
        
        $episodes['titles'] = $validTitles;
        
        $firstEpisodeOffset = $validEpisodes[0][1];
        $mainContent = substr($content, 0, $firstEpisodeOffset);
        
        if (trim($mainContent) && strlen(trim($mainContent)) > 20) {
            $episodes['count'] = count($validEpisodes) + 1;
            $episodes['content'][] = trim($mainContent);
        } else {
            $episodes['count'] = count($validEpisodes);
        }
        
        for ($i = 0; $i < count($validEpisodes); $i++) {
            $currentOffset = $validEpisodes[$i][1];
            $nextOffset = isset($validEpisodes[$i + 1]) ? $validEpisodes[$i + 1][1] : strlen($content);
            $episodeContent = substr($content, $currentOffset, $nextOffset - $currentOffset);
            $episodes['content'][] = trim($episodeContent);
        }
        
        return $episodes;
    }
    
    private function getExcerpt($content) {
        $plainText = preg_replace('/^#+\s*/m', '', $content);
        $plainText = preg_replace('/\*\*(.*?)\*\*/s', '$1', $plainText);
        $plainText = preg_replace('/\*(.*?)\*/s', '$1', $plainText);
        $plainText = preg_replace('/\n+/', ' ', $plainText);
        $plainText = strip_tags(trim($plainText));
        return strlen($plainText) > 300 ? substr($plainText, 0, 300) . '...' : $plainText;
    }
    
    public function getStoryByFilename($filename) {
        if (!preg_match('/^[a-zA-Z0-9\-_]+\.md$/', $filename)) return null;
        
        $filepath = $this->contentDir . '/' . $filename;
        if (!file_exists($filepath) || !is_file($filepath)) return null;
        
        $content = file_get_contents($filepath);
        $story = $this->parseMarkdown($content, $filename);
        
        if ($story && $story['date'] !== null) {
            $storyTime = strtotime($story['date']);
            if ($storyTime !== false && $storyTime > time()) return null;
        }
        
        return $story;
    }
}

$storyManager = new StoryManager();
$stories = $storyManager->getStories();

if (isset($_GET['action']) && $_GET['action'] === 'get_story' && isset($_GET['filename'])) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    
    $story = $storyManager->getStoryByFilename($_GET['filename']);
    
    if ($story === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Verhaal niet gevonden']);
    } else {
        echo json_encode($story);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Mijn verhalen</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Roboto',sans-serif;background-color:hsl(60,2.7%,14.5%);color:hsl(48,33.3%,97.1%);line-height:1.6;min-height:100vh}
        .container{max-width:800px;margin:0 auto;padding:2rem}
        .header{text-align:center;margin-bottom:3rem;border-bottom:2px solid hsl(30,3.3%,11.8%);padding-bottom:2rem}
        .header h1{color:hsl(48,33.3%,97.1%);font-size:2.5rem;margin-bottom:0.5rem;font-weight:300}
        .header p{color:hsl(50,9%,73.7%);font-size:1.1rem}
        .story-list{display:block}
        .story-list.hidden{display:none}
        .story-preview{background-color:hsl(60,2.1%,18.4%);border-radius:8px;padding:2rem;margin-bottom:2rem;border-left:4px solid hsl(15,63.1%,59.6%);transition:all 0.3s ease;cursor:pointer}
        .story-preview:hover{background-color:hsl(60,2.6%,7.6%);transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.3)}
        .story-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem}
        .story-title{color:hsl(48,33.3%,97.1%);font-size:1.5rem;font-weight:500;flex:1}
        .episode-indicator{display:flex;align-items:center;gap:0.5rem;margin-left:1rem;flex-shrink:0}
        .episode-dots{display:flex;gap:0.3rem}
        .episode-dot{width:8px;height:8px;border-radius:50%;background-color:hsl(15,63.1%,59.6%);opacity:0.6}
        .episode-dot.published{background-color:hsl(120,60%,40%);opacity:1}
        .episode-dot.unpublished{background-color:hsl(0,0%,50%);opacity:0.4}
        .episode-count{color:hsl(50,9%,73.7%);font-size:0.85rem;font-weight:500}
        .published-indicator{background-color:hsl(120,60%,30%);color:hsl(120,60%,95%);padding:0.2rem 0.5rem;border-radius:3px;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.5px}
        .story-navigation .published-indicator{margin-left:auto}
        .story-excerpt{color:hsl(50,9%,73.7%);font-size:1rem;line-height:1.7}
        .story-full{display:none;background-color:hsl(60,2.1%,18.4%);border-radius:8px;padding:2rem;border-left:4px solid hsl(15,63.1%,59.6%)}
        .story-full.active{display:block}
        .story-navigation{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem}
        .back-button{background-color:hsl(15,63.1%,59.6%);color:hsl(48,33.3%,97.1%);border:none;padding:0.75rem 1.5rem;border-radius:6px;cursor:pointer;font-size:1rem;transition:background-color 0.3s ease}
        .back-button:hover{background-color:hsl(15,55.6%,52.4%)}
        .episode-navigation{display:flex;align-items:center;gap:1rem}
        .episode-selector{display:flex;gap:0.5rem}
        .episode-button{background-color:hsl(30,3.3%,11.8%);color:hsl(48,33.3%,97.1%);border:1px solid hsl(60,2.6%,7.6%);padding:0.5rem 1rem;border-radius:4px;cursor:pointer;font-size:0.9rem;transition:all 0.3s ease}
        .episode-button:hover{background-color:hsl(60,2.6%,7.6%)}
        .episode-button.active{background-color:hsl(15,63.1%,59.6%);color:hsl(48,33.3%,97.1%);border-color:hsl(15,63.1%,59.6%)}
        .episode-button.unpublished-episode{background-color:hsl(0,0%,25%);color:hsl(0,0%,60%);border-color:hsl(0,0%,35%);font-style:italic}
        .episode-button.unpublished-episode:hover{background-color:hsl(0,0%,30%)}
        .story-full h1{color:hsl(50,9%,73.7%);font-size:2rem;margin-bottom:2rem;font-weight:500}
        .story-full h2{color:hsl(50,9%,73.7%);font-size:1.5rem;margin:2rem 0 1rem 0;font-weight:500}
        .story-full h3{color:hsl(50,9%,73.7%);font-size:1.2rem;margin:1.5rem 0 1rem 0;font-weight:500}
        .story-full p{margin-bottom:1.5rem;color:hsl(50,9%,73.7%);text-align:left}
        .story-full em{color:hsl(50,9%,73.7%);font-style:italic}
        .story-full strong{color:hsl(50,9%,73.7%);font-weight:600}
        .story-full blockquote{background-color:hsl(30,3.3%,11.8%);border-left:4px solid hsl(15,63.1%,59.6%);margin:1.5rem 0;padding:1rem 1.5rem;font-style:italic;color:hsl(50,9%,80%)}
        .story-full blockquote p{margin-bottom:0;color:inherit}
        .story-full blockquote p:last-child{margin-bottom:0}
        .story-bottom-nav{margin-top:3rem;padding-top:2rem;border-top:2px solid hsl(30,3.3%,11.8%);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem}
        .story-bottom-nav .back-button{background-color:hsl(15,63.1%,59.6%);color:hsl(48,33.3%,97.1%);border:none;padding:0.75rem 1.5rem;border-radius:6px;cursor:pointer;font-size:1rem;transition:background-color 0.3s ease}
        .story-bottom-nav .back-button:hover{background-color:hsl(15,55.6%,52.4%)}
        .story-bottom-nav .episode-selector{display:flex;gap:0.5rem;flex-wrap:wrap}
        .error{background-color:hsl(0,23%,15.6%);color:hsl(0,73.1%,66.5%);padding:1rem;border-radius:6px;margin:2rem 0;border-left:4px solid hsl(0,58.6%,34.1%)}
        hr{border:none;height:2px;background-color:hsl(50,9%,73.7%);margin:3rem 0}
        @media (max-width:768px){
            .container{padding:1rem}
            .header h1{font-size:2rem}
            .story-preview,.story-full{padding:1.5rem}
            .story-header{flex-direction:column;align-items:flex-start;gap:0.5rem}
            .episode-indicator{margin-left:0}
            .story-navigation{flex-direction:column;align-items:stretch}
            .episode-navigation{justify-content:center}
            .episode-selector{flex-wrap:wrap}
            .story-bottom-nav{flex-direction:column;align-items:stretch;text-align:center}
            .story-bottom-nav .episode-selector{justify-content:center}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mijn verhalen</h1>
            <p>Een collectie van verhalen</p>
        </div>

        <div id="story-list" class="story-list">
            <?php if (empty($stories)): ?>
                <div class="error">
                    <h3>Geen verhalen gevonden</h3>
                    <p>Zorg ervoor dat je markdown bestanden in de 'content' map staan.</p>
                </div>
            <?php else: ?>
                <?php foreach ($stories as $story): ?>
                    <div class="story-preview" onclick="showStory('<?php echo htmlspecialchars($story['filename']); ?>')">
                        <div class="story-header">
                            <h2 class="story-title"><?php echo htmlspecialchars($story['title']); ?></h2>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <?php if ($story['published'] || (!empty($story['published_episodes']))): ?>
                                    <span class="published-indicator">
                                        <?php if (!empty($story['published_episodes'])): ?>
                                            <?php 
                                            $publishedCount = count($story['published_episodes']);
                                            $totalCount = $story['episodes']['count'];
                                            if ($publishedCount == $totalCount) {
                                                echo "Volledig gepubliceerd";
                                            } else {
                                                echo "Deel " . implode(', ', $story['published_episodes']) . " gepubliceerd";
                                            }
                                            ?>
                                        <?php else: ?>
                                            Gepubliceerd
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($story['episodes']['count'] > 1): ?>
                                    <div class="episode-indicator">
                                        <div class="episode-dots">
                                            <?php for ($i = 0; $i < min($story['episodes']['count'], 5); $i++): ?>
                                                <?php 
                                                $episodeNumber = $i + 1;
                                                $isPublished = true;
                                                if (!empty($story['published_episodes'])) {
                                                    $isPublished = in_array($episodeNumber, $story['published_episodes']);
                                                }
                                                $dotClass = 'episode-dot ' . ($isPublished ? 'published' : 'unpublished');
                                                ?>
                                                <div class="<?php echo $dotClass; ?>" title="Deel <?php echo $episodeNumber; ?><?php echo $isPublished ? ' (gepubliceerd)' : ' (niet gepubliceerd)'; ?>"></div>
                                            <?php endfor; ?>
                                            <?php if ($story['episodes']['count'] > 5): ?>
                                                <span class="episode-count">+</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="episode-count"><?php echo $story['episodes']['count']; ?> delen</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="story-excerpt"><?php echo htmlspecialchars($story['excerpt']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="story-full" class="story-full">
            <div class="story-navigation">
                <button class="back-button" onclick="showList()">← Terug naar verhalen</button>
                <div class="episode-navigation" id="episode-navigation" style="display: none;">
                    <div class="episode-selector" id="episode-selector"></div>
                </div>
                <span id="published-indicator" class="published-indicator" style="display: none;">Gepubliceerd</span>
            </div>
            <div id="story-content"></div>
        </div>
    </div>

    <script>
        marked.setOptions({sanitize: false, gfm: true, breaks: true, headerIds: false});

        let currentStory = null;
        let currentEpisode = 0;

        function isEpisodePublished(episodeNumber) {
            if (currentStory.published_episodes && currentStory.published_episodes.length > 0) {
                return currentStory.published_episodes.includes(episodeNumber);
            }
            return currentStory.published;
        }

        function updatePublishedIndicator(episodeNumber) {
            const indicator = document.getElementById('published-indicator');
            const isPublished = isEpisodePublished(episodeNumber);
            
            indicator.style.display = 'block';
            indicator.textContent = isPublished ? 'Gepubliceerd' : 'Niet gepubliceerd';
            indicator.style.backgroundColor = isPublished ? 'hsl(120, 60%, 30%)' : 'hsl(0, 60%, 40%)';
        }

        async function showStory(filename) {
            try {
                const response = await fetch(`?action=get_story&filename=${encodeURIComponent(filename)}`);
                if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                
                const story = await response.json();
                if (story.error) throw new Error(story.error);

                currentStory = story;
                currentEpisode = 0;
                
                document.getElementById('story-list').classList.add('hidden');
                document.getElementById('story-full').classList.add('active');
                
                setupEpisodeNavigation();
                updatePublishedIndicator(1);
                showEpisode(0);
                window.scrollTo(0, 0);
            } catch (error) {
                console.error('Fout bij het laden van verhaal:', error);
                alert('Fout bij het laden van verhaal: ' + error.message);
            }
        }

        function setupEpisodeNavigation() {
            const episodeNav = document.getElementById('episode-navigation');
            const episodeSelector = document.getElementById('episode-selector');
            
            if (currentStory.episodes.count <= 1) {
                episodeNav.style.display = 'none';
                return;
            }
            
            episodeNav.style.display = 'flex';
            episodeSelector.innerHTML = '';
            
            for (let i = 0; i < currentStory.episodes.count; i++) {
                const episodeNumber = i + 1;
                const isPublished = isEpisodePublished(episodeNumber);
                
                const button = document.createElement('button');
                button.className = 'episode-button' + (i === 0 ? ' active' : '') + (!isPublished ? ' unpublished-episode' : '');
                button.textContent = `Deel ${episodeNumber}${!isPublished ? ' (niet gepubliceerd)' : ''}`;
                button.onclick = () => showEpisode(i);
                episodeSelector.appendChild(button);
            }
        }

        function showEpisode(episodeIndex) {
            currentEpisode = episodeIndex;
            const episodeNumber = episodeIndex + 1;
            
            document.querySelectorAll('.episode-button').forEach((btn, index) => {
                btn.classList.toggle('active', index === episodeIndex);
            });
            
            updatePublishedIndicator(episodeNumber);
            
            let content = `<h1>${currentStory.title}</h1>`;
            
            const episodeContent = currentStory.episodes.content[episodeIndex] || currentStory.body;
            
            if (episodeIndex === 0) {
                const adjustedContent = episodeContent.replace(/^#\s+/gm, '## ');
                content += marked.parse(adjustedContent);
            } else {
                const adjustedContent = episodeContent.replace(/^#\s+/gm, '## ');
                content += marked.parse(adjustedContent);
            }
            
            content += createBottomNavigation();
            document.getElementById('story-content').innerHTML = content;
            window.scrollTo(0, 0);
        }

        function createBottomNavigation() {
            let bottomNav = '<div class="story-bottom-nav"><button class="back-button" onclick="showList()">← Terug naar verhalen</button>';
            
            if (currentStory.episodes.count > 1) {
                bottomNav += '<div class="episode-selector">';
                for (let i = 0; i < currentStory.episodes.count; i++) {
                    const episodeNumber = i + 1;
                    const isPublished = isEpisodePublished(episodeNumber);
                    const activeClass = i === currentEpisode ? ' active' : '';
                    const unpublishedClass = !isPublished ? ' unpublished-episode' : '';
                    const episodeText = !isPublished ? ' (niet gepubliceerd)' : '';
                    bottomNav += `<button class="episode-button${activeClass}${unpublishedClass}" onclick="showEpisode(${i})">Deel ${episodeNumber}${episodeText}</button>`;
                }
                bottomNav += '</div>';
            }
            
            const episodeNumber = currentEpisode + 1;
            const isCurrentPublished = isEpisodePublished(episodeNumber);
            const bgColor = isCurrentPublished ? 'hsl(120, 60%, 30%)' : 'hsl(0, 60%, 40%)';
            const text = isCurrentPublished ? 'Gepubliceerd' : 'Niet gepubliceerd';
            bottomNav += `<span class="published-indicator" style="background-color: ${bgColor};">${text}</span>`;
            
            return bottomNav + '</div>';
        }

        function showList() {
            document.getElementById('story-full').classList.remove('active');
            document.getElementById('story-list').classList.remove('hidden');
            window.scrollTo(0, 0);
        }
    </script>
</body>
</html>