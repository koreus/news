<?php declare(strict_types=1);

namespace XoopsModules\News;

/**
 * This file contains the Keyhighlighter class that highlight the chosen keyword in the current output buffer.
 */

/**
 * Keyhighlighter class
 *
 * This class highlight the chosen keywords in the current output buffer
 *
 * @author    Setec Astronomy
 * @abstract  Highlight specific keywords.
 * @copyright 2004
 * @example   sample.php A sample code.
 * @link      https://setecastronomy.stufftoread.com
 */
class Keyhighlighter
{
    /**
     * @access private
     */
    public string $preg_keywords = '';
    /**
     * @access private
     */
    public string $keywords = '';
    /**
     * @access private
     */
    public bool $singlewords = false;
    /**
     * @access private
     */
    public $replace_callback = null;
    public $content;
    /**
     * Main constructor
     *
     * This is the main constructor of Keyhighlighter class. <br>
     * It's the only public method of the class.
     *
     * @param string        $keywords         the keywords you want to highlight
     * @param bool|null     $singlewords      specify if it has to highlight also the single words.
     * @param callback|null $replace_callback a custom callback for keyword highlight.
     *                                   <code>
     *                                   <?php
     *                                   require_once ('Keyhighlighter.class.php');
     *
     * function my_highlighter ($matches) {
     *    return '<span style="font-weight: bolder; color: #FF0000;">' . $matches[0] . '</span>';
     * }
     *
     * new Keyhighlighter ('W3C', false, 'my_highlighter');
     * readfile ('https://www.w3c.org/');
     * ?>
     * </code>
     */
    // public function __construct ()
    public function __construct(string $keywords, ?bool $singlewords = null, callable $replace_callback = null)
    {
        $singlewords            ??= false;
        $this->keywords         = $keywords;
        $this->singlewords      = $singlewords;
        $this->replace_callback = $replace_callback;
    }

    /**
     * @access private
     * @param array $replace_matches
     * @return mixed
     */
    public function replace(array $replace_matches)
    {
        $patterns = [];
        if ($this->singlewords) {
            $keywords = \explode(' ', $this->preg_keywords);
            foreach ($keywords as $keyword) {
                $patterns[] = '/(?' . '>' . $keyword . '+)/si';
            }
        } else {
            $patterns[] = '/(?' . '>' . $this->preg_keywords . '+)/si';
        }

        $result = $replace_matches[0];

        foreach ($patterns as $pattern) {
            if (null !== $this->replace_callback) {
                $result = \preg_replace_callback($pattern, [$this, 'replace_callback'], $result);
            } else {
                $result = \preg_replace($pattern, '<span class="highlightedkey">\\0</span>', $result);
            }
        }

        return $result;
    }

    /**
     * @access private
     * @param string $buffer
     * @return string
     */
    public function highlight(string $buffer): string
    {
        $buffer              = '>' . $buffer . '<';
        $this->preg_keywords = \preg_replace('/[^\w ]/i', '', $this->keywords);
        $buffer              = \preg_replace_callback('/(\>(((?' . '>[^>i', [&$this, 'replace'], $buffer);
        $buffer              = \xoops_substr($buffer, 1, -1);

        return $buffer;
    }
}
