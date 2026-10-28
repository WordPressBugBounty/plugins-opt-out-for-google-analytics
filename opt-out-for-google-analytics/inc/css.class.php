<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    /**
     * Checks, formats and minifies the custom CSS. Only whitespace, comments (when minified) and missing semicolons are changed, never the selectors or values.
     * The same check is done in the admin JavaScript (assets/admin.js), to show the errors while typing.
     */
    class GAOO_CSS {

        /**
         * @var string CSS code.
         */
        private $css;

        /**
         * @var array Found errors, each with the keys "code", "line" and "text".
         */
        private $errors = array();

        /**
         * @param string $css CSS code.
         */
        private function __construct( $css ) {
            $this->css = str_replace( array( "\r\n", "\r" ), "\n", (string) $css );
        }

        /**
         * Check the CSS and format it, if it is valid.
         *
         * @param string $css CSS code.
         *
         * @return array List with the keys "errors" (list of errors, empty if valid) and "css" (formatted code, or the original code if not valid).
         */
        public static function check( $css ) {
            $instance = new self( $css );
            $tree     = $instance->parse();

            // Sort the errors by line, e.g. a block which is not closed is found at the end.
            usort( $instance->errors, function ( $a, $b ) {
                return $a[ 'line' ] - $b[ 'line' ];
            } );

            return array(
                'errors' => $instance->errors,
                'css'    => empty( $instance->errors ) ? $instance->render( $tree->children, 0 ) : $css,
            );
        }

        /**
         * Minify the CSS for the website: without comments and unnecessary whitespace. The selectors and values are not changed.
         *
         * @param string $css CSS code.
         *
         * @return string Minified code, or the original code if it is not valid.
         */
        public static function minify( $css ) {
            $instance = new self( $css );
            $tree     = $instance->parse();

            return empty( $instance->errors ) ? $instance->render_minified( $tree->children ) : trim( (string) $css );
        }

        /**
         * Messages of the errors, the first placeholder is the line number.
         *
         * @return array Messages by error code.
         */
        public static function messages() {
            return array(
                /* translators: %1$d: line number */
                'comment'     => __( 'Line %1$d: The comment is not closed with "*/".', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'string'      => __( 'Line %1$d: The text in quotes is not closed.', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'paren_open'  => __( 'Line %1$d: The bracket "(" is not closed with ")".', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'paren_close' => __( 'Line %1$d: There is a ")" without an opening bracket "(".', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'selector'    => __( 'Line %1$d: The selector before "{" is missing.', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'brace_open'  => __( 'Line %1$d: The "{" after the selector is missing.', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'brace_close' => __( 'Line %1$d: There is a "}" without an opening "{".', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'block'       => __( 'Line %1$d: The block, which starts here, is not closed with "}".', 'opt-out-for-google-analytics' ),
                /* translators: %1$d: line number */
                'semicolon'   => __( 'Line %1$d: The ";" at the end of the line is missing.', 'opt-out-for-google-analytics' ),
                /* translators: 1: line number, 2: invalid code */
                'declaration' => __( 'Line %1$d: "%2$s" is not valid, expected "property: value;".', 'opt-out-for-google-analytics' ),
            );
        }

        /**
         * Text of an error.
         *
         * @param array $error Error with the keys "code", "line" and "text".
         *
         * @return string Message of the error.
         */
        public static function message( $error ) {
            $messages = self::messages();

            return sprintf( $messages[ $error[ 'code' ] ], $error[ 'line' ], $error[ 'text' ] );
        }

        /**
         * Add an error.
         *
         * @param string $code Code of the error, see messages().
         * @param int    $line Line of the error.
         * @param string $text Code with the error, if needed by the message. (Default: empty)
         */
        private function error( $code, $line, $text = '' ) {
            $this->errors[] = array(
                'code' => $code,
                'line' => $line,
                'text' => $text,
            );
        }

        /**
         * Parse the CSS into a tree of rules, declarations, statements and comments.
         *
         * @return object Root node with the list "children".
         */
        private function parse() {
            $css         = $this->css;
            $length      = strlen( $css );
            $root        = (object) array( 'type' => 'root', 'children' => array(), 'line' => 0 );
            $stack       = array( $root );
            $buffer      = '';
            $buffer_line = 0;
            $parens      = array();
            $line        = 1;
            $i           = 0;

            while ( $i < $length ) {
                $char    = $css[ $i ];
                $current = end( $stack );

                // Comment: kept as its own node, or as part of the selector / value.
                if ( '/' === $char && '*' === substr( $css, $i + 1, 1 ) ) {
                    $end = strpos( $css, '*/', $i + 2 );

                    if ( false === $end ) {
                        $this->error( 'comment', $line );

                        return $root;
                    }

                    $comment = substr( $css, $i, $end + 2 - $i );

                    if ( '' === trim( $buffer ) ) {
                        $current->children[] = (object) array( 'type' => 'comment', 'text' => $comment, 'line' => $line );
                        $buffer              = '';
                    }
                    else {
                        $buffer .= $comment;
                    }

                    $line += substr_count( $comment, "\n" );
                    $i    = $end + 2;
                    continue;
                }

                // Text in quotes, with escaped characters.
                if ( '"' === $char || "'" === $char ) {
                    $end = $i + 1;

                    while ( $end < $length && $css[ $end ] !== $char && "\n" !== $css[ $end ] ) {
                        $end += ( '\\' === $css[ $end ] ) ? 2 : 1;
                    }

                    if ( $end >= $length || "\n" === $css[ $end ] ) {
                        $this->error( 'string', $line );

                        return $root;
                    }

                    $buffer_line = '' === trim( $buffer ) ? $line : $buffer_line;
                    $buffer      .= substr( $css, $i, $end + 1 - $i );
                    $i           = $end + 1;
                    continue;
                }

                if ( '\\' === $char ) {
                    $buffer_line = '' === trim( $buffer ) ? $line : $buffer_line;
                    $buffer      .= substr( $css, $i, 2 );
                    $line        += substr_count( substr( $css, $i + 1, 1 ), "\n" );
                    $i           += 2;
                    continue;
                }

                if ( '(' === $char ) {
                    $parens[] = $line;
                }
                elseif ( ')' === $char ) {
                    if ( empty( $parens ) ) {
                        $this->error( 'paren_close', $line );

                        return $root;
                    }

                    array_pop( $parens );
                }
                // Inside of brackets, e.g. url(data:image/png;base64,...), the characters have no special meaning.
                elseif ( empty( $parens ) && '{' === $char ) {
                    $prelude = trim( $buffer );

                    if ( '' === $prelude ) {
                        $this->error( 'selector', $line );
                    }
                    elseif ( 'root' !== $current->type ) {
                        $this->check_nested_prelude( $prelude, $buffer_line );
                    }

                    $rule                = (object) array( 'type' => 'rule', 'prelude' => $prelude, 'children' => array(), 'line' => '' === $prelude ? $line : $buffer_line );
                    $current->children[] = $rule;
                    $stack[]             = $rule;
                    $buffer              = '';
                    $i++;
                    continue;
                }
                elseif ( empty( $parens ) && ( ';' === $char || '}' === $char ) ) {
                    $text = trim( $buffer );

                    if ( '' !== $text ) {
                        $this->add_declaration( $current, $text, $buffer_line, ';' === $char );
                    }

                    if ( '}' === $char ) {
                        if ( count( $stack ) < 2 ) {
                            $this->error( 'brace_close', $line );
                        }
                        else {
                            array_pop( $stack );
                        }
                    }

                    $buffer = '';
                    $i++;
                    continue;
                }

                if ( "\n" === $char ) {
                    $line++;
                }
                elseif ( '' === trim( $buffer ) && '' !== trim( $char ) ) {
                    $buffer_line = $line;
                }

                $buffer .= $char;
                $i++;
            }

            if ( ! empty( $parens ) ) {
                $this->error( 'paren_open', end( $parens ) );

                return $root;
            }

            $text = trim( $buffer );

            if ( '' !== $text ) {
                if ( count( $stack ) < 2 ) {
                    $this->error( '@' === $text[ 0 ] ? 'semicolon' : 'brace_open', $buffer_line );
                }
                else {
                    $this->add_declaration( end( $stack ), $text, $buffer_line, false );
                }
            }

            // Blocks, which are not closed.
            foreach ( array_slice( $stack, 1 ) as $rule ) {
                $this->error( 'block', $rule->line );
            }

            return $root;
        }

        /**
         * Add a declaration ("property: value") or a statement (e.g. @import) to a node.
         *
         * @param object $node       Node of the rule.
         * @param string $text       Code of the declaration.
         * @param int    $line       Line of the declaration.
         * @param bool   $semicolon  True if the declaration ends with a semicolon.
         */
        private function add_declaration( $node, $text, $line, $semicolon ) {
            // Statements like @import or @charset.
            if ( '@' === $text[ 0 ] ) {
                $node->children[] = (object) array( 'type' => 'statement', 'text' => $text, 'line' => $line );

                return;
            }

            // Declarations are only allowed inside of a rule.
            if ( 'root' === $node->type ) {
                $this->error( 'brace_open', $line );

                return;
            }

            if ( ! preg_match( '/^(--[\w-]+|[*_]?-?[a-zA-Z][\w-]*)\s*:/', $text, $matches ) ) {
                $this->error( 'declaration', $line, self::shorten( $text ) );

                return;
            }

            // A new declaration in a following line means that the semicolon at the end of a line is missing.
            $masked = self::mask( $text );

            if ( preg_match( '/\n\s*(--[\w-]+|-?[a-zA-Z][\w-]*)\s*:/', $masked, $match, PREG_OFFSET_CAPTURE ) ) {
                $before = rtrim( substr( $masked, 0, $match[ 0 ][ 1 ] ) );
                $this->error( 'semicolon', $line + substr_count( $before, "\n" ) );

                return;
            }

            $value = trim( substr( $text, strlen( $matches[ 0 ] ) ) );

            if ( '' === $value && 0 !== strpos( $matches[ 1 ], '--' ) ) {
                $this->error( 'declaration', $line, self::shorten( $text ) );

                return;
            }

            $node->children[] = (object) array( 'type' => 'declaration', 'property' => $matches[ 1 ], 'value' => $value, 'line' => $line );
        }

        /**
         * Selector of a nested rule. If it starts with a declaration in its own line, the semicolon is missing, e.g. "color: red" followed by "&:hover {".
         *
         * @param string $prelude Selector.
         * @param int    $line    Line of the selector.
         */
        private function check_nested_prelude( $prelude, $line ) {
            $masked = self::mask( $prelude );
            $first  = strstr( $masked, "\n", true );

            // Pseudo classes like a:hover have no whitespace after the colon, declarations usually have.
            if ( false !== $first && preg_match( '/^(--[\w-]+|-?[a-zA-Z][\w-]*)\s*:\s+\S/', trim( $first ) ) ) {
                $this->error( 'semicolon', $line );
            }
        }

        /**
         * Replace the content of comments, quotes and brackets with spaces, so their characters are ignored. Line breaks are kept.
         *
         * @param string $text Code.
         *
         * @return string Masked code with the same length.
         */
        private static function mask( $text ) {
            return preg_replace_callback( '/\/\*.*?\*\/|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|\((?:[^()]|(?R))*\)/s', function ( $match ) {
                return preg_replace( '/[^\n]/', ' ', $match[ 0 ] );
            }, $text );
        }

        /**
         * Shorten the code for the error message.
         *
         * @param string $text Code.
         *
         * @return string The first 40 characters in one line.
         */
        private static function shorten( $text ) {
            $text = preg_replace( '/\s+/', ' ', $text );

            return strlen( $text ) > 40 ? substr( $text, 0, 40 ) . '…' : $text;
        }

        /**
         * Collapse whitespace to a single space, except in quotes and comments.
         *
         * @param string $text Code.
         *
         * @return string Code in one line.
         */
        private static function collapse( $text ) {
            $parts = preg_split( '/(\/\*.*?\*\/|"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\')/s', trim( $text ), -1, PREG_SPLIT_DELIM_CAPTURE );

            foreach ( $parts as $key => $part ) {
                if ( 0 === $key % 2 ) {
                    $parts[ $key ] = preg_replace( '/\s+/', ' ', $part );
                }
            }

            return implode( '', $parts );
        }

        /**
         * Split selectors at the commas, except the commas in quotes or brackets, e.g. :is(.a, .b).
         *
         * @param string $prelude Selectors.
         *
         * @return array List of selectors.
         */
        private static function split_selectors( $prelude ) {
            $masked    = self::mask( $prelude );
            $selectors = array();
            $start     = 0;

            while ( false !== ( $comma = strpos( $masked, ',', $start ) ) ) {
                $selectors[] = substr( $prelude, $start, $comma - $start );
                $start       = $comma + 1;
            }

            $selectors[] = substr( $prelude, $start );

            return array_values( array_filter( array_map( array( __CLASS__, 'collapse' ), $selectors ), 'strlen' ) );
        }

        /**
         * Render the nodes as minified CSS, the comments are removed.
         *
         * @param array $nodes    List of nodes.
         * @param bool  $in_block True for the content of a rule. (Default: false)
         *
         * @return string Minified CSS.
         */
        private function render_minified( $nodes, $in_block = false ) {
            $parts = array();

            foreach ( $nodes as $node ) {
                switch ( $node->type ) {
                    case 'statement':
                        $parts[] = self::collapse( $node->text ) . ';';
                        break;

                    case 'declaration':
                        $parts[] = $node->property . ':' . self::collapse( $node->value ) . ';';
                        break;

                    case 'rule':
                        $prelude = '@' === $node->prelude[ 0 ] ? self::collapse( $node->prelude ) : implode( ',', self::split_selectors( $node->prelude ) );
                        $parts[] = $prelude . '{' . $this->render_minified( $node->children, true ) . '}';
                        break;
                }
            }

            // The last semicolon in a block is not needed.
            return $in_block ? rtrim( implode( '', $parts ), ';' ) : implode( '', $parts );
        }

        /**
         * Render the nodes as formatted CSS: one declaration per line, indented with 4 spaces, one selector per line.
         *
         * @param array $nodes List of nodes.
         * @param int   $depth Depth of the nodes.
         *
         * @return string Formatted CSS.
         */
        private function render( $nodes, $depth ) {
            $indent   = str_repeat( '    ', $depth );
            $lines    = array();
            $previous = null;

            foreach ( $nodes as $node ) {
                // Empty line between the rules, a comment belongs to the following rule.
                if ( null !== $previous && ( 'rule' === $previous->type || ( 'rule' === $node->type && 'comment' !== $previous->type ) || ( 0 === $depth && 'comment' === $node->type && 'comment' !== $previous->type ) ) ) {
                    $lines[] = '';
                }

                switch ( $node->type ) {
                    case 'comment':
                        $lines[] = $indent . $node->text;
                        break;

                    case 'statement':
                        $lines[] = $indent . self::collapse( $node->text ) . ';';
                        break;

                    case 'declaration':
                        $lines[] = $indent . $node->property . ': ' . self::collapse( $node->value ) . ';';
                        break;

                    case 'rule':
                        $prelude = '@' === $node->prelude[ 0 ] ? self::collapse( $node->prelude ) : implode( ",\n" . $indent, self::split_selectors( $node->prelude ) );
                        $lines[] = $indent . $prelude . ' {';

                        if ( ! empty( $node->children ) ) {
                            $lines[] = $this->render( $node->children, $depth + 1 );
                        }

                        $lines[] = $indent . '}';
                        break;
                }

                $previous = $node;
            }

            return implode( "\n", $lines );
        }
    }
