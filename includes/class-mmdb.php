<?php
/**
 * Minimal reader for MaxMind DB (.mmdb) files, which is the format DB-IP uses too.
 * Pure PHP and read-only. It seeks into the file, so the database never has to fit in memory.
 *
 * Supports the record sizes 24, 28 and 32 and every data type in the spec.
 */

namespace SNN_Analytics;

defined( 'ABSPATH' ) || exit;

final class MMDB {

	const MARKER = "\xAB\xCD\xEFMaxMind.com";

	private $fh;
	private int $size;
	private int $node_count;
	private int $record_size;
	private int $node_bytes;
	private int $tree_size;
	private int $ip_version;
	private int $data_start;

	public function __construct( string $file ) {
		$fh = @fopen( $file, 'rb' );
		if ( $fh === false ) {
			throw new \RuntimeException( 'Cannot open the geo database.' );
		}
		$this->fh   = $fh;
		$this->size = (int) filesize( $file );

		$meta_start = $this->find_metadata();
		fseek( $this->fh, $meta_start );
		$meta = $this->decode_at( $meta_start + strlen( self::MARKER ) );

		if ( ! is_array( $meta ) || ! isset( $meta['node_count'], $meta['record_size'], $meta['ip_version'] ) ) {
			throw new \RuntimeException( 'The file is not a valid MMDB database.' );
		}

		$this->node_count  = (int) $meta['node_count'];
		$this->record_size = (int) $meta['record_size'];
		$this->ip_version  = (int) $meta['ip_version'];
		$this->node_bytes  = intdiv( $this->record_size * 2, 8 );
		$this->tree_size   = $this->node_count * $this->node_bytes;
		$this->data_start  = $this->tree_size + 16;
	}

	public function __destruct() {
		if ( is_resource( $this->fh ) ) {
			fclose( $this->fh );
		}
	}

	public function ip_version(): int {
		return $this->ip_version;
	}

	/**
	 * Returns the record for an IP address as an array, or null when there is none.
	 */
	public function lookup( string $ip ): ?array {
		$bin = @inet_pton( $ip );
		if ( $bin === false ) {
			return null;
		}

		// An IPv4 address in an IPv6 database is stored as ::a.b.c.d.
		if ( strlen( $bin ) === 4 && $this->ip_version === 6 ) {
			$bin = str_repeat( "\0", 12 ) . $bin;
		}
		if ( strlen( $bin ) === 16 && $this->ip_version === 4 ) {
			return null;
		}

		$bits = strlen( $bin ) * 8;
		$node = 0;

		for ( $i = 0; $i < $bits && $node < $this->node_count; $i++ ) {
			$bit  = ( ord( $bin[ $i >> 3 ] ) >> ( 7 - ( $i & 7 ) ) ) & 1;
			$node = $this->read_node( $node, $bit );
		}

		if ( $node === $this->node_count || $node < $this->node_count ) {
			return null;
		}

		$offset = $this->tree_size + ( $node - $this->node_count );
		$value  = $this->decode_at( $offset );

		return is_array( $value ) ? $value : null;
	}

	private function read_node( int $node, int $bit ): int {
		fseek( $this->fh, $node * $this->node_bytes );
		$b = unpack( 'C*', (string) fread( $this->fh, $this->node_bytes ) );

		switch ( $this->record_size ) {
			case 24:
				$left  = ( $b[1] << 16 ) | ( $b[2] << 8 ) | $b[3];
				$right = ( $b[4] << 16 ) | ( $b[5] << 8 ) | $b[6];
				break;
			case 28:
				$left  = ( ( $b[4] & 0xF0 ) << 20 ) | ( $b[1] << 16 ) | ( $b[2] << 8 ) | $b[3];
				$right = ( ( $b[4] & 0x0F ) << 24 ) | ( $b[5] << 16 ) | ( $b[6] << 8 ) | $b[7];
				break;
			case 32:
				$left  = ( $b[1] << 24 ) | ( $b[2] << 16 ) | ( $b[3] << 8 ) | $b[4];
				$right = ( $b[5] << 24 ) | ( $b[6] << 16 ) | ( $b[7] << 8 ) | $b[8];
				break;
			default:
				throw new \RuntimeException( 'Unsupported record size.' );
		}

		return $bit ? $right : $left;
	}

	/** Offset of the metadata marker, searched from the end of the file (it sits in the last 128 KB). */
	private function find_metadata(): int {
		$window = min( $this->size, 131072 );
		fseek( $this->fh, $this->size - $window );
		$tail = (string) fread( $this->fh, $window );

		$pos = strrpos( $tail, self::MARKER );
		if ( $pos === false ) {
			throw new \RuntimeException( 'The file is not a valid MMDB database.' );
		}

		return $this->size - $window + $pos;
	}

	// ------------------------------------------------------------- decoding

	/** Decodes the value at an absolute file offset. */
	private function decode_at( int $offset ): mixed {
		$pos = $offset;

		return $this->decode( $pos );
	}

	/**
	 * @param int $pos Moved forward past the decoded value. Pointers are followed without moving it further.
	 */
	private function decode( int &$pos ): mixed {
		$ctrl = $this->byte( $pos );
		$type = $ctrl >> 5;

		if ( $type === 1 ) { // pointer
			return $this->pointer( $ctrl, $pos );
		}

		if ( $type === 0 ) { // extended type, stored in the next byte
			$type = 7 + $this->byte( $pos );
		}

		$size = $ctrl & 0x1F;
		if ( $size === 29 ) {
			$size = 29 + $this->byte( $pos );
		} elseif ( $size === 30 ) {
			$size = 285 + $this->uint( $pos, 2 );
		} elseif ( $size === 31 ) {
			$size = 65821 + $this->uint( $pos, 3 );
		}

		switch ( $type ) {
			case 2: // UTF-8 string
				return $this->take( $pos, $size );
			case 3: // double
				return unpack( 'E', $this->take( $pos, 8 ) )[1];
			case 4: // bytes
				return $this->take( $pos, $size );
			case 5: // uint16
			case 6: // uint32
			case 9: // uint64
			case 10: // uint128
				return $this->uint( $pos, $size );
			case 7: // map
				$map = [];
				for ( $i = 0; $i < $size; $i++ ) {
					$key       = $this->decode( $pos );
					$map[ $key ] = $this->decode( $pos );
				}
				return $map;
			case 8: // int32
				$value = $this->uint( $pos, $size );
				return $size === 4 && $value >= 0x80000000 ? $value - 0x100000000 : $value;
			case 11: // array
				$list = [];
				for ( $i = 0; $i < $size; $i++ ) {
					$list[] = $this->decode( $pos );
				}
				return $list;
			case 14: // boolean, the value is the size itself
				return $size === 1;
			case 15: // float
				return unpack( 'G', $this->take( $pos, 4 ) )[1];
			default:
				throw new \RuntimeException( 'Unsupported MMDB data type.' );
		}
	}

	private function pointer( int $ctrl, int &$pos ): mixed {
		$size  = ( $ctrl >> 3 ) & 0x03;
		$vvv   = $ctrl & 0x07;

		switch ( $size ) {
			case 0:
				$ptr = ( $vvv << 8 ) | $this->byte( $pos );
				break;
			case 1:
				$ptr = ( ( $vvv << 16 ) | $this->uint( $pos, 2 ) ) + 2048;
				break;
			case 2:
				$ptr = ( ( $vvv << 24 ) | $this->uint( $pos, 3 ) ) + 526336;
				break;
			default:
				$ptr = $this->uint( $pos, 4 );
		}

		$target = $this->data_start + $ptr;
		$inner  = $target;

		return $this->decode( $inner );
	}

	private function byte( int &$pos ): int {
		fseek( $this->fh, $pos );
		$pos++;

		return ord( (string) fread( $this->fh, 1 ) );
	}

	private function take( int &$pos, int $length ): string {
		// A zero-length value is legal (uint16 = 0 is stored with no payload), and fread() rejects 0.
		if ( $length <= 0 ) {
			return '';
		}

		fseek( $this->fh, $pos );
		$pos += $length;

		return (string) fread( $this->fh, $length );
	}

	private function uint( int &$pos, int $length ): int {
		$value = 0;
		foreach ( unpack( 'C*', $this->take( $pos, $length ) ) ?: [] as $b ) {
			$value = ( $value << 8 ) | $b;
		}

		return $value;
	}
}
