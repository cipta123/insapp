<?php
/**
 * Redis Cache Manager for High Performance Caching
 * Replaces file-based cache with Redis for better performance
 */

class RedisCache {
    private $redis;
    private $connected = false;
    private $prefix = 'insapp:';
    private $defaultTTL = 300; // 5 minutes
    
    public function __construct($host = '127.0.0.1', $port = 6379, $password = null) {
        try {
            if (!extension_loaded('redis')) {
                throw new Exception('Redis extension not installed');
            }
            
            $this->redis = new Redis();
            $this->redis->connect($host, $port);
            
            if ($password) {
                $this->redis->auth($password);
            }
            
            // Test connection
            $this->redis->ping();
            $this->connected = true;
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->connected = false;
        }
    }
    
    /**
     * Check if Redis is available
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * Get data from cache
     */
    public function get($key) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $data = $this->redis->get($this->prefix . $key);
            return $data ? json_decode($data, true) : false;
        } catch (Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set data to cache
     */
    public function set($key, $data, $ttl = null) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $ttl = $ttl ?? $this->defaultTTL;
            $encoded = json_encode($data);
            return $this->redis->setex($this->prefix . $key, $ttl, $encoded);
        } catch (Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete from cache
     */
    public function delete($key) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            return $this->redis->del($this->prefix . $key);
        } catch (Exception $e) {
            error_log("Redis delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear cache by pattern
     */
    public function clearPattern($pattern) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $keys = $this->redis->keys($this->prefix . $pattern);
            if (!empty($keys)) {
                return $this->redis->del($keys);
            }
            return true;
        } catch (Exception $e) {
            error_log("Redis clear pattern error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        if (!$this->connected) {
            return ['connected' => false];
        }
        
        try {
            $info = $this->redis->info();
            return [
                'connected' => true,
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info)
            ];
        } catch (Exception $e) {
            error_log("Redis stats error: " . $e->getMessage());
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate($info) {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total == 0) return 0;
        return round(($hits / $total) * 100, 2);
    }
    
    /**
     * Increment counter (for analytics)
     */
    public function increment($key, $amount = 1) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($this->prefix . $key, $amount);
        } catch (Exception $e) {
            error_log("Redis increment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set with expiration at specific time
     */
    public function setExpireAt($key, $data, $timestamp) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $encoded = json_encode($data);
            $this->redis->set($this->prefix . $key, $encoded);
            return $this->redis->expireAt($this->prefix . $key, $timestamp);
        } catch (Exception $e) {
            error_log("Redis setExpireAt error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple keys at once
     */
    public function getMultiple($keys) {
        if (!$this->connected) {
            return [];
        }
        
        try {
            $prefixedKeys = array_map(function($key) {
                return $this->prefix . $key;
            }, $keys);
            
            $values = $this->redis->mget($prefixedKeys);
            $result = [];
            
            foreach ($keys as $index => $key) {
                $result[$key] = $values[$index] ? json_decode($values[$index], true) : false;
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Redis getMultiple error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Fallback to file cache if Redis unavailable
     */
    public function getWithFallback($key, $fallbackCallback = null) {
        // Try Redis first
        $data = $this->get($key);
        if ($data !== false) {
            return $data;
        }
        
        // Try file cache fallback
        $fileCache = "cache/{$key}.json";
        if (file_exists($fileCache) && (time() - filemtime($fileCache)) < $this->defaultTTL) {
            $data = json_decode(file_get_contents($fileCache), true);
            if ($data) {
                // Store back to Redis if available
                $this->set($key, $data);
                return $data;
            }
        }
        
        // Execute fallback callback if provided
        if ($fallbackCallback && is_callable($fallbackCallback)) {
            $data = $fallbackCallback();
            if ($data) {
                $this->set($key, $data);
                // Also save to file as backup
                if (!file_exists('cache')) {
                    mkdir('cache', 0755, true);
                }
                file_put_contents($fileCache, json_encode($data));
            }
            return $data;
        }
        
        return false;
    }
}
?>
