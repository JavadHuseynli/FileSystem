<?php
// Simple Cache System

class SimpleCache {
    private $cache_dir;
    private $default_ttl = 3600; // 1 hour

    public function __construct($cache_dir = null) {
        if (!$cache_dir) {
            $this->cache_dir = __DIR__ . '/../cache';
        } else {
            $this->cache_dir = $cache_dir;
        }

        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    public function get($key) {
        $cache_file = $this->getCacheFile($key);

        if (!file_exists($cache_file)) {
            return null;
        }

        $data = unserialize(file_get_contents($cache_file));

        // Check if expired
        if ($data['expires'] < time()) {
            unlink($cache_file);
            return null;
        }

        return $data['value'];
    }

    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }

        $cache_file = $this->getCacheFile($key);

        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];

        return file_put_contents($cache_file, serialize($data)) !== false;
    }

    public function delete($key) {
        $cache_file = $this->getCacheFile($key);

        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }

        return false;
    }

    public function clear() {
        $files = glob($this->cache_dir . '/cache_*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function getCacheFile($key) {
        $hash = md5($key);
        return $this->cache_dir . '/cache_' . $hash;
    }

    public function cleanExpired() {
        $files = glob($this->cache_dir . '/cache_*');
        $cleaned = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $data = unserialize(file_get_contents($file));
                if ($data['expires'] < time()) {
                    unlink($file);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}

// Helper functions
function cache_get($key) {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache->get($key);
}

function cache_set($key, $value, $ttl = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache->set($key, $value, $ttl);
}

function cache_remember($key, $callback, $ttl = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache->remember($key, $callback, $ttl);
}

function cache_delete($key) {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache->delete($key);
}

function cache_clear() {
    static $cache = null;
    if ($cache === null) {
        $cache = new SimpleCache();
    }
    return $cache->clear();
}
?>
