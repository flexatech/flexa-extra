<?php
/**
 * Minimal WordPress / WooCommerce function stubs for the unit suite.
 *
 * Only the functions actually reached by the classes under test are defined,
 * with behaviour close enough to WordPress to make the assertions meaningful
 * (e.g. sanitize_text_field really strips tags, is_email really validates).
 *
 * Option-set "post meta" is backed by a global registry the tests populate via
 * Flexa\Extra\Tests\Support\OptionSetFactory.
 */

declare(strict_types=1);

$GLOBALS['fx_sets']          = array(); // id => ['name','status','fields','targeting'].
$GLOBALS['fx_product_terms'] = array(); // product_id => [ taxonomy => [term_ids] ].

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) {
        unset( $domain );
        return $text;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) {
        unset( $domain );
        return $text;
    }
}

if ( ! function_exists( '_n' ) ) {
    function _n( $single, $plural, $number, $domain = 'default' ) {
        unset( $domain );
        return 1 === (int) $number ? $single : $plural;
    }
}

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $tag, $value = null, ...$args ) {
        unset( $tag, $args );
        return $value;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        if ( ! is_scalar( $str ) ) {
            return '';
        }
        $str = wp_strip_all_tags( (string) $str );
        $str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
        return trim( (string) $str );
    }
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $string ) {
        return trim( (string) preg_replace( '/<[^>]*>/', '', (string) $string ) );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $title ) {
        $title = strtolower( (string) $title );
        $title = preg_replace( '/[^a-z0-9_\s\-]/', '', $title );
        $title = preg_replace( '/[\s_]+/', '-', (string) $title );
        return trim( (string) $title, '-' );
    }
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
    function sanitize_html_class( $class ) {
        return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );
    }
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
    function sanitize_hex_color( $color ) {
        $color = (string) $color;
        if ( '' === $color ) {
            return '';
        }
        return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ? $color : null;
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw( $url ) {
        return trim( (string) $url );
    }
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $n ) {
        return abs( (int) $n );
    }
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
    // Mirrors WordPress core: only the strings "false" and "0" coerce to false.
    function rest_sanitize_boolean( $value ) {
        if ( is_string( $value ) && in_array( strtolower( $value ), array( 'false', '0' ), true ) ) {
            return false;
        }
        return (bool) $value;
    }
}

if ( ! function_exists( 'is_email' ) ) {
    function is_email( $email ) {
        return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
    }
}

if ( ! function_exists( 'wc_is_valid_url' ) ) {
    function wc_is_valid_url( $url ) {
        $url = (string) $url;
        if ( ! in_array( wp_parse_scheme( $url ), array( 'http', 'https' ), true ) ) {
            return false;
        }
        return (bool) filter_var( $url, FILTER_VALIDATE_URL );
    }
}

if ( ! function_exists( 'wp_parse_scheme' ) ) {
    function wp_parse_scheme( $url ) {
        $scheme = wp_parse_url_component( $url, PHP_URL_SCHEME );
        return $scheme ? strtolower( $scheme ) : '';
    }
}

if ( ! function_exists( 'wp_parse_url_component' ) ) {
    function wp_parse_url_component( $url, $component ) {
        $parsed = parse_url( (string) $url );
        $map    = array( PHP_URL_SCHEME => 'scheme', PHP_URL_HOST => 'host' );
        $key    = $map[ $component ] ?? null;
        return $key && isset( $parsed[ $key ] ) ? $parsed[ $key ] : null;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $options = 0, $depth = 512 ) {
        return json_encode( $data, $options, $depth );
    }
}

/* --- Option-set "post meta" backed by the test registry ------------------- */

if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args = array() ) {
        $ids = array();
        foreach ( $GLOBALS['fx_sets'] as $id => $set ) {
            if ( isset( $args['meta_key'], $args['meta_value'] ) && '_flexa_extra_status' === $args['meta_key'] ) {
                if ( (string) ( $set['status'] ?? 0 ) !== (string) $args['meta_value'] ) {
                    continue;
                }
            }
            $ids[] = $id;
        }
        return $ids;
    }
}

if ( ! function_exists( 'get_post_meta' ) ) {
    function get_post_meta( $id, $key = '', $single = false ) {
        unset( $single );
        $set = $GLOBALS['fx_sets'][ $id ] ?? null;
        if ( null === $set ) {
            return '';
        }
        switch ( $key ) {
            case '_flexa_extra_fields':
                return $set['fields'] ?? array();
            case '_flexa_extra_targeting':
                return $set['targeting'] ?? array();
            case '_flexa_extra_status':
                return (string) ( $set['status'] ?? 0 );
            case '_flexa_extra_actions':
                return $set['actions'] ?? array();
        }
        return '';
    }
}

if ( ! function_exists( 'update_post_meta' ) ) {
    function update_post_meta( $id, $key, $value ) {
        if ( ! isset( $GLOBALS['fx_sets'][ $id ] ) ) {
            return false;
        }
        switch ( $key ) {
            case '_flexa_extra_fields':
                $GLOBALS['fx_sets'][ $id ]['fields'] = $value;
                break;
            case '_flexa_extra_targeting':
                $GLOBALS['fx_sets'][ $id ]['targeting'] = $value;
                break;
            case '_flexa_extra_status':
                $GLOBALS['fx_sets'][ $id ]['status'] = $value;
                break;
            case '_flexa_extra_actions':
                $GLOBALS['fx_sets'][ $id ]['actions'] = $value;
                break;
        }
        return true;
    }
}

if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $id ) {
        return $GLOBALS['fx_sets'][ $id ]['name'] ?? '';
    }
}

if ( ! function_exists( 'has_term' ) ) {
    function has_term( $term, $taxonomy, $product_id ) {
        $terms = $GLOBALS['fx_product_terms'][ $product_id ][ $taxonomy ] ?? array();
        return in_array( (int) $term, array_map( 'intval', $terms ), true );
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        return array_key_exists( $key, $GLOBALS['fx_options'] ?? array() )
            ? $GLOBALS['fx_options'][ $key ]
            : $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value ) {
        $GLOBALS['fx_options'][ $key ] = $value;
        return true;
    }
}
