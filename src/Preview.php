<?php

declare(strict_types=1);

namespace Shoofly;

//! Lightweight template engine
class Preview extends View
{
    protected //! token filter
        $filter = [
            'c' => '$this->c',
            'esc' => '$this->esc',
            'raw' => '$this->raw',
            'export' => 'Base::instance()->export',
            'alias' => 'Base::instance()->alias',
            'format' => 'Base::instance()->format'
        ];

    protected //! newline interpolation
        $interpolation = true;

    /**
     * Enable/disable markup parsing interpolation
     * mainly used for adding appropriate newlines
     * @param $bool bool
     */
    public function interpolation($bool)
    {
        $this->interpolation = $bool;
    }

    /**
    *   Return C-locale equivalent of number
    *   @return string
    *   @param $val int|float
    **/
    public function c($val)
    {
        $locale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, 'C');
        $out = (string)(float)$val;
        $locale = setlocale(LC_NUMERIC, $locale);
        return $out;
    }

    /**
    *   Convert token to variable
    *   @return string
    *   @param $str string
    **/
    public function token($str)
    {
        $str = trim(preg_replace('/\{\{(.+?)\}\}/s', '\1', $this->fw->compile($str)));
        if (
            preg_match(
                '/^(.+)(?<!\|)\|((?:\h*\w+(?:\h*[,;]?))+)$/s',
                $str,
                $parts
            )
        ) {
            $str = trim($parts[1]);
            foreach ($this->fw->split(trim($parts[2], "\xC2\xA0")) as $func) {
                $str = ((empty($this->filter[$cmd = $func]) &&
                function_exists($cmd)) ||
                    is_string($cmd = $this->filter($func))) ?
                    $cmd . '(' . $str . ')' :
                    'Base::instance()->' .
                        'call($this->filter(\'' . $func . '\'),[' . $str . '])';
            }
        }
        return $str;
    }

    /**
    *   Register or get (one specific or all) token filters
    *   @param string $key
    *   @param string|closure $func
    *   @return array|closure|string
    */
    public function filter($key = null, $func = null)
    {
        if (!$key) {
            return array_keys($this->filter);
        }
        $key = strtolower($key);
        if (!$func) {
            return $this->filter[$key];
        }
        $this->filter[$key] = $func;
    }

    /**
    *   Assemble markup
    *   @return string
    *   @param $node string
    **/
    protected function build($node)
    {
        return preg_replace_callback(
            '/\{~(.+?)~\}|\{\*(.+?)\*\}|\{\-(.+?)\-\}|' .
            '\{\{(.+?)\}\}((\r?\n)*)/s',
            function ($expr) {
                if ($expr[1]) {
                    $str = '<?php ' . $this->token($expr[1]) . ' ?>';
                } elseif ($expr[2]) {
                    return '';
                } elseif ($expr[3]) {
                    $str = $expr[3];
                } else {
                    $str = '<?= (' . trim($this->token($expr[4])) . ')' .
                        ($this->interpolation ?
                            (!empty($expr[6]) ? '."' . $expr[6] . '"' : '') : '') . ' ?>';
                    if (isset($expr[5])) {
                        $str .= $expr[5];
                    }
                }
                return $str;
            },
            $node
        );
    }

    /**
    *   Render template string
    *   @return string
    *   @param $node string|array
    *   @param $hive array
    *   @param $ttl int
    *   @param $persist bool
    *   @param $escape bool
    **/
    public function resolve($node, array $hive = null, $ttl = 0, $persist = false, $escape = null)
    {
        $hash = null;
        $fw = $this->fw;
        $cache = Cache::instance();
        if ($escape !== null) {
            $esc = $fw->ESCAPE;
            $fw->ESCAPE = $escape;
        }
        if ($ttl || $persist) {
            $hash = $fw->hash($fw->serialize($node));
        }
        if ($ttl && $cache->exists($hash, $data)) {
            return $data;
        }
        if ($persist) {
            if (!is_dir($tmp = $fw->TEMP)) {
                mkdir($tmp, Base::MODE, true);
            }
            if (
                !is_file($this->file = ($tmp .
                $fw->SEED . '.' . $hash . '.php'))
            ) {
                $fw->write($this->file, $this->build($node));
            }
            if (
                isset($_COOKIE[session_name()]) &&
                !headers_sent() && session_status() != PHP_SESSION_ACTIVE
            ) {
                session_start();
            }
            $fw->sync('SESSION');
            $data = $this->sandbox($hive);
        } else {
            if (!$hive) {
                $hive = $fw->hive();
            }
            if ($fw->ESCAPE) {
                $hive = $this->esc($hive);
            }
            extract($hive);
            unset($hive);
            ob_start();
            eval(' ?>' . $this->build($node) . '<?php ');
            $data = ob_get_clean();
        }
        if ($ttl) {
            $cache->set($hash, $data, $ttl);
        }
        if ($escape !== null) {
            $fw->ESCAPE = $esc;
        }
        return $data;
    }

    /**
     *  Parse template string
     *  @return string
     *  @param $text string
     **/
    public function parse($text)
    {
        // Remove PHP code and comments
        return preg_replace(
            '/\h*<\?(?!xml)(?:php|\s*=)?.+?\?>\h*|' .
            '\{\*.+?\*\}/is',
            '',
            $text
        );
    }

    /**
    *   Render template
    *   @return string
    *   @param $file string
    *   @param $mime string
    *   @param $hive array
    *   @param $ttl int
    **/
    public function render($file, $mime = 'text/html', array $hive = null, $ttl = 0)
    {
        $fw = $this->fw;
        $cache = Cache::instance();
        if (!is_dir($tmp = $fw->TEMP)) {
            mkdir($tmp, Base::MODE, true);
        }
        foreach ($fw->split($fw->UI) as $dir) {
            if ($cache->exists($hash = $fw->hash($dir . $file), $data)) {
                return $data;
            }
            if (is_file($view = $fw->fixslashes($dir . $file))) {
                if (
                    !is_file($this->file = ($tmp .
                    $fw->SEED . '.' . $fw->hash($view) . '.php')) ||
                    filemtime($this->file) < filemtime($view)
                ) {
                    $contents = $fw->read($view);
                    if (isset($this->trigger['beforerender'])) {
                        foreach ($this->trigger['beforerender'] as $func) {
                            $contents = $fw->call($func, [$contents, $view]);
                        }
                    }
                    $text = $this->parse($contents);
                    $fw->write($this->file, $this->build($text));
                }
                if (
                    isset($_COOKIE[session_name()]) &&
                    !headers_sent() && session_status() != PHP_SESSION_ACTIVE
                ) {
                    session_start();
                }
                $fw->sync('SESSION');
                $data = $this->sandbox($hive, $mime);
                if (isset($this->trigger['afterrender'])) {
                    foreach ($this->trigger['afterrender'] as $func) {
                        $data = $fw->call($func, [$data, $view]);
                    }
                }
                if ($ttl) {
                    $cache->set($hash, $data, $ttl);
                }
                return $data;
            }
        }
        user_error(sprintf(Base::E_Open, $file), E_USER_ERROR);
    }

    /**
     *  post rendering handler
     *  @param $func callback
     */
    public function beforerender($func)
    {
        $this->trigger['beforerender'][] = $func;
    }
}
