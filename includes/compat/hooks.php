<?php
/**
 * WordPress Hook System Compatibility Layer
 * Implements add_action, do_action, apply_filters, add_filter
 */

declare(strict_types=1);

if (!class_exists('ARM_Hook_System')) {
    class ARM_Hook_System
    {
        private static ?self $instance = null;
        private array $actions = [];
        private array $filters = [];
        private array $current_filter = [];

        public static function getInstance(): self
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function addAction(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
        {
            return $this->addFilter($hook, $callback, $priority, $acceptedArgs);
        }

        public function addFilter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
        {
            if (!isset($this->filters[$hook][$priority])) {
                $this->filters[$hook][$priority] = [];
            }

            $idx = $this->buildUniqueId($callback);
            $this->filters[$hook][$priority][$idx] = [
                'function' => $callback,
                'accepted_args' => $acceptedArgs
            ];

            return true;
        }

        public function removeAction(string $hook, $callback, int $priority = 10): bool
        {
            return $this->removeFilter($hook, $callback, $priority);
        }

        public function removeFilter(string $hook, $callback, int $priority = 10): bool
        {
            $idx = $this->buildUniqueId($callback);

            if (isset($this->filters[$hook][$priority][$idx])) {
                unset($this->filters[$hook][$priority][$idx]);
                if (empty($this->filters[$hook][$priority])) {
                    unset($this->filters[$hook][$priority]);
                }
                return true;
            }

            return false;
        }

        public function hasAction(string $hook, $callback = false)
        {
            return $this->hasFilter($hook, $callback);
        }

        public function hasFilter(string $hook, $callback = false)
        {
            if (!isset($this->filters[$hook])) {
                return false;
            }

            if ($callback === false) {
                return true;
            }

            $idx = $this->buildUniqueId($callback);

            foreach ($this->filters[$hook] as $priority => $callbacks) {
                if (isset($callbacks[$idx])) {
                    return $priority;
                }
            }

            return false;
        }

        public function doAction(string $hook, ...$args): void
        {
            $this->current_filter[] = $hook;

            if (!isset($this->filters[$hook])) {
                array_pop($this->current_filter);
                return;
            }

            ksort($this->filters[$hook]);

            foreach ($this->filters[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $function = $callback['function'];
                    $acceptedArgs = $callback['accepted_args'];

                    $callbackArgs = array_slice($args, 0, $acceptedArgs);
                    call_user_func_array($function, $callbackArgs);
                }
            }

            array_pop($this->current_filter);
        }

        public function applyFilters(string $hook, $value, ...$args)
        {
            $this->current_filter[] = $hook;

            if (!isset($this->filters[$hook])) {
                array_pop($this->current_filter);
                return $value;
            }

            ksort($this->filters[$hook]);

            $allArgs = array_merge([$value], $args);

            foreach ($this->filters[$hook] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $function = $callback['function'];
                    $acceptedArgs = $callback['accepted_args'];

                    $callbackArgs = array_slice($allArgs, 0, $acceptedArgs);
                    $value = call_user_func_array($function, $callbackArgs);
                    $allArgs[0] = $value;
                }
            }

            array_pop($this->current_filter);

            return $value;
        }

        public function currentFilter(): string
        {
            return end($this->current_filter) ?: '';
        }

        public function doingFilter(string $hook = null): bool
        {
            if ($hook === null) {
                return !empty($this->current_filter);
            }
            return in_array($hook, $this->current_filter, true);
        }

        private function buildUniqueId($callback): string
        {
            if (is_string($callback)) {
                return $callback;
            }

            if (is_object($callback)) {
                return spl_object_hash($callback);
            }

            if (is_array($callback)) {
                if (is_object($callback[0])) {
                    return spl_object_hash($callback[0]) . '::' . $callback[1];
                }
                return $callback[0] . '::' . $callback[1];
            }

            return serialize($callback);
        }
    }
}

// Global functions
if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return ARM_Hook_System::getInstance()->addAction($hook, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        ARM_Hook_System::getInstance()->doAction($hook, ...$args);
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return ARM_Hook_System::getInstance()->addFilter($hook, $callback, $priority, $acceptedArgs);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        return ARM_Hook_System::getInstance()->applyFilters($hook, $value, ...$args);
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, $callback, int $priority = 10): bool
    {
        return ARM_Hook_System::getInstance()->removeAction($hook, $callback, $priority);
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $hook, $callback, int $priority = 10): bool
    {
        return ARM_Hook_System::getInstance()->removeFilter($hook, $callback, $priority);
    }
}

if (!function_exists('has_action')) {
    function has_action(string $hook, $callback = false)
    {
        return ARM_Hook_System::getInstance()->hasAction($hook, $callback);
    }
}

if (!function_exists('has_filter')) {
    function has_filter(string $hook, $callback = false)
    {
        return ARM_Hook_System::getInstance()->hasFilter($hook, $callback);
    }
}

if (!function_exists('current_filter')) {
    function current_filter(): string
    {
        return ARM_Hook_System::getInstance()->currentFilter();
    }
}

if (!function_exists('doing_action')) {
    function doing_action(string $hook = null): bool
    {
        return ARM_Hook_System::getInstance()->doingFilter($hook);
    }
}

if (!function_exists('doing_filter')) {
    function doing_filter(string $hook = null): bool
    {
        return ARM_Hook_System::getInstance()->doingFilter($hook);
    }
}
