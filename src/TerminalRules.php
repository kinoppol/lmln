<?php
declare(strict_types=1);

/**
 * Server-side re-check of a lesson task's completion rule against a submitted
 * command history + virtual-filesystem snapshot from the client terminal.
 *
 * VFS node shape (matches public/js/terminal-engine.js):
 *   directory: {"t":"d","ch":{"name":<node>,...},"p":"rwxr-xr-x"}
 *   file:      {"t":"f","c":"file contents","p":"rw-r--r--"}
 *
 * Rule param shapes:
 *   history_regex     {pattern:"/^pwd$/", min?:1}
 *   vfs_path_exists   {path:["~","project"]}
 *   vfs_path_absent   {path:["~","trash","old.log"]}
 *   vfs_perm_check    {path:["~","script.sh"], perm?:"rw-------", contains?:"x"}
 *   vfs_path_exists_any {paths:[["~","backup.txt"],["~","documents","backup.txt"]]}
 *   all_of            {rules:[{type:"...", params:{...}}, ...]}
 */
final class TerminalRules
{
    public static function evaluate(string $type, array $params, array $history, ?array $vfs): bool
    {
        switch ($type) {
            case 'history_regex':
                return self::historyRegex($history, $params);
            case 'vfs_path_exists':
                return self::node($vfs, $params['path'] ?? []) !== null;
            case 'vfs_path_absent':
                return self::node($vfs, $params['path'] ?? []) === null;
            case 'vfs_perm_check':
                return self::permCheck($vfs, $params);
            case 'vfs_path_exists_any':
                foreach (($params['paths'] ?? []) as $path) {
                    if (self::node($vfs, $path) !== null) {
                        return true;
                    }
                }
                return false;
            case 'all_of':
                foreach (($params['rules'] ?? []) as $rule) {
                    if (!self::evaluate($rule['type'], $rule['params'] ?? [], $history, $vfs)) {
                        return false;
                    }
                }
                return true;
            default:
                return false;
        }
    }

    private static function historyRegex(array $history, array $params): bool
    {
        $pattern = $params['pattern'] ?? null;
        if (!$pattern) {
            return false;
        }
        $min = (int)($params['min'] ?? 1);
        $count = 0;
        foreach ($history as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (@preg_match($pattern, trim($line)) === 1) {
                $count++;
                if ($count >= $min) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function permCheck(?array $vfs, array $params): bool
    {
        $node = self::node($vfs, $params['path'] ?? []);
        if ($node === null || !isset($node['p'])) {
            return false;
        }
        $perm = (string)$node['p'];
        if (isset($params['perm'])) {
            return $perm === $params['perm'];
        }
        if (isset($params['contains'])) {
            return strpos($perm, (string)$params['contains']) !== false;
        }
        return false;
    }

    /**
     * Resolve a path array like ["~","documents","backup.txt"] against the
     * VFS root {t:d, ch:{home:{t:d, ch:{student:{t:d, ch:{...}}}}}}.
     * "~" resolves to home/student.
     */
    public static function node(?array $vfs, array $path): ?array
    {
        if ($vfs === null) {
            return null;
        }
        $parts = [];
        foreach ($path as $seg) {
            if ($seg === '~') {
                $parts[] = 'home';
                $parts[] = 'student';
            } elseif ($seg !== '' && $seg !== '.') {
                $parts[] = $seg;
            }
        }
        $cur = $vfs;
        foreach ($parts as $seg) {
            if (!isset($cur['t']) || $cur['t'] !== 'd' || !isset($cur['ch'][$seg])) {
                return null;
            }
            $cur = $cur['ch'][$seg];
        }
        return $cur;
    }
}
