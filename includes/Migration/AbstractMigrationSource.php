<?php
namespace Flexa\Extra\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * One importable source (another options plugin). A source knows how to read its
 * own stored data (WordPress-dependent) and how to convert one of its option
 * sets into Flexa Extra's raw input shape (pure, so it is unit-testable).
 *
 * The two halves are kept apart on purpose: read_raw() touches the database, but
 * convert() is a plain array-to-array transform. That is where the mapping logic
 * lives and where the tests point.
 */
abstract class AbstractMigrationSource {

    /** Machine slug used in REST payloads (e.g. "yayextra"). */
    abstract public function slug(): string;

    /** Human label shown in the import screen. */
    abstract public function label(): string;

    /** Whether this plugin's data is present on the site. */
    abstract public function is_available(): bool;

    /**
     * Read the source's option sets from the database, each as a plain array
     * ready for {@see self::convert()}.
     *
     * @return list<array<string,mixed>>
     */
    abstract public function read_raw(): array;

    /**
     * Convert one source option set into Flexa Extra's raw input plus a list of
     * human-readable warnings about anything that could not be mapped 1:1.
     *
     * @param array<string,mixed> $set
     * @return array{input:array<string,mixed>,warnings:list<string>}
     */
    abstract public function convert( array $set ): array;

    /** How many option sets this source would import. */
    public function count(): int {
        return $this->is_available() ? count( $this->read_raw() ) : 0;
    }

    // --- Safe array accessors (source data is untrusted / loosely shaped) -----

    /**
     * @param array<string,mixed> $arr
     */
    protected static function str( array $arr, string $key, string $default = '' ): string {
        return isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? (string) $arr[ $key ] : $default;
    }

    /**
     * @param array<string,mixed> $arr
     */
    protected static function bool( array $arr, string $key, bool $default = false ): bool {
        if ( ! array_key_exists( $key, $arr ) ) {
            return $default;
        }
        $value = $arr[ $key ];
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( is_numeric( $value ) ) {
            return (float) $value !== 0.0;
        }
        return in_array( $value, [ 'true', 'yes', 'on', '1' ], true );
    }

    /**
     * @param array<string,mixed> $arr
     */
    protected static function num( array $arr, string $key, ?float $default = null ): ?float {
        return isset( $arr[ $key ] ) && is_numeric( $arr[ $key ] ) ? (float) $arr[ $key ] : $default;
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<int|string,mixed>
     */
    protected static function arr( array $arr, string $key ): array {
        return isset( $arr[ $key ] ) && is_array( $arr[ $key ] ) ? $arr[ $key ] : [];
    }

    /**
     * A price rule in Flexa Extra's shape.
     *
     * @return array{type:string,amount:float}
     */
    protected static function price( string $type, float $amount ): array {
        return [ 'type' => $type, 'amount' => $amount ];
    }

    /**
     * Empty conditional-logic block (no rules = always shown), so every field
     * carries a well-formed `logic` key for the sanitizer.
     *
     * @return array{enabled:bool,action:string,match:string,rules:array<int,mixed>}
     */
    protected static function no_logic(): array {
        return [ 'enabled' => false, 'action' => 'show', 'match' => 'any', 'rules' => [] ];
    }
}
