<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Session types. Each type is an independent booking configuration: its own slot
 * length, weekly availability, client email flows and (optionally) a Google Meet
 * video link. Types are stored as an ordered list in the `tsb_types` option.
 *
 * The global form fields, consent, captcha and Google OAuth credentials live in
 * `tsb_settings` (TSB_Availability::settings) and are shared by every type.
 *
 * Backward compatibility: when no types exist yet, a single `default` type is
 * synthesised from the legacy global settings, so an upgraded site behaves
 * exactly as before until the admin defines more types.
 */
class TSB_Types {

	const OPTION = 'tsb_types';

	/** Keys a type copies verbatim from the legacy global settings when seeded. */
	const AVAIL_KEYS = array(
		'slot_minutes', 'slot_gap', 'base_start', 'base_end', 'week',
		'emails', 'reminder_hours', 'ics_attach', 'ics_summary', 'ics_location',
	);

	/** Per-type defaults (used to fill gaps in a stored/incoming type). */
	public static function defaults() {
		return array(
			'id'                => '',
			'label'             => '',
			'enabled'           => 1,
			'order'             => 0,
			'description'       => '',
			'slot_minutes'      => 30,
			'slot_gap'          => 0,
			'base_start'        => 9,
			'base_end'          => 17,
			'week'              => TSB_Availability::default_week(),
			'emails'            => class_exists( 'TSB_Emails' ) ? TSB_Emails::default_templates() : array(),
			'reminder_hours'    => 24,
			'ics_attach'        => 1,
			'ics_summary'       => 'Booking: {{name}}',
			'ics_location'      => '',
			'meet_enabled'      => 0,
		);
	}

	/** Synthesize the `default` type from the legacy global settings. */
	public static function default_type() {
		$s = TSB_Availability::settings();
		$t = array_merge( self::defaults(), array(
			'id'           => 'default',
			'label'        => __( 'Default', 'tsb' ),
			'enabled'      => 1,
			'order'        => 0,
			'meet_enabled' => 0,
		) );
		foreach ( self::AVAIL_KEYS as $k ) {
			if ( isset( $s[ $k ] ) ) {
				$t[ $k ] = $s[ $k ];
			}
		}
		return self::normalize( $t );
	}

	/** All types, keyed by id, in stored order. Falls back to a single default. */
	public static function all() {
		$raw = get_option( self::OPTION, null );
		$out = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $t ) {
				$n = self::normalize( $t );
				if ( $n && '' !== $n['id'] && ! isset( $out[ $n['id'] ] ) ) {
					$out[ $n['id'] ] = $n;
				}
			}
		}
		if ( empty( $out ) ) {
			$d              = self::default_type();
			$out[ $d['id'] ] = $d;
		}
		return $out;
	}

	/** One type by id, or the first available type when the id is unknown. */
	public static function get( $id ) {
		$all = self::all();
		if ( isset( $all[ $id ] ) ) {
			return $all[ $id ];
		}
		return reset( $all );
	}

	public static function exists( $id ) {
		$all = self::all();
		return isset( $all[ $id ] );
	}

	/** Enabled types, ordered by `order` then label. */
	public static function enabled() {
		$out = array_filter( self::all(), function ( $t ) {
			return ! empty( $t['enabled'] );
		} );
		uasort( $out, function ( $a, $b ) {
			if ( $a['order'] === $b['order'] ) {
				return strcmp( $a['label'], $b['label'] );
			}
			return $a['order'] <=> $b['order'];
		} );
		return $out;
	}

	/** Coerce an arbitrary array into a clean type config. */
	public static function normalize( $t ) {
		if ( ! is_array( $t ) ) {
			return null;
		}
		$def = self::defaults();
		$out = array_merge( $def, array_intersect_key( $t, $def ) );

		$out['id']    = sanitize_key( $t['id'] ?? '' );
		$out['label'] = sanitize_text_field( $t['label'] ?? '' );
		if ( '' === $out['id'] && '' !== $out['label'] ) {
			$out['id'] = self::slugify( $out['label'] );
		}
		if ( '' === $out['label'] && '' !== $out['id'] ) {
			$out['label'] = $out['id'];
		}
		$out['description'] = sanitize_text_field( $t['description'] ?? '' );

		$out['enabled']      = empty( $t['enabled'] ) ? 0 : 1;
		$out['meet_enabled'] = empty( $t['meet_enabled'] ) ? 0 : 1;
		$out['order']        = (int) ( $t['order'] ?? 0 );

		$out['slot_minutes'] = max( 5, (int) $out['slot_minutes'] );
		$out['slot_gap']     = max( 0, (int) $out['slot_gap'] );
		$out['base_start']   = max( 0, min( 23, (int) $out['base_start'] ) );
		$out['base_end']     = max( 1, min( 24, (int) $out['base_end'] ) );
		$out['reminder_hours'] = max( 1, (int) $out['reminder_hours'] );
		$out['ics_attach']     = empty( $out['ics_attach'] ) ? 0 : 1;
		$out['ics_summary']    = sanitize_text_field( $out['ics_summary'] );
		$out['ics_location']   = sanitize_text_field( $out['ics_location'] );

		$out['week'] = self::normalize_week( $out['week'] );

		if ( ! is_array( $out['emails'] ) ) {
			$out['emails'] = array();
		}
		if ( class_exists( 'TSB_Emails' ) ) {
			$def_em = TSB_Emails::default_templates();
			foreach ( $def_em as $k => $v ) {
				$out['emails'][ $k ] = isset( $out['emails'][ $k ] )
					? array_merge( $v, (array) $out['emails'][ $k ] )
					: $v;
			}
		}

		return $out;
	}

	protected static function normalize_week( $week ) {
		$out = array();
		for ( $d = 1; $d <= 7; $d++ ) {
			$wd        = is_array( $week ) && isset( $week[ $d ] ) ? $week[ $d ] : array();
			$out[ $d ] = array(
				'open'     => empty( $wd['open'] ) ? 0 : 1,
				'use_base' => empty( $wd['use_base'] ) ? 0 : 1,
				'start'    => max( 0, min( 23, (int) ( $wd['start'] ?? 9 ) ) ),
				'end'      => max( 1, min( 24, (int) ( $wd['end'] ?? 17 ) ) ),
			);
		}
		return $out;
	}

	/** A URL-safe, unique slug from a label. */
	public static function slugify( $label, $existing = array() ) {
		$base = sanitize_key( str_replace( ' ', '-', strtolower( $label ) ) );
		if ( '' === $base ) {
			$base = 'type';
		}
		$id  = $base;
		$n   = 2;
		while ( in_array( $id, $existing, true ) ) {
			$id = $base . '-' . $n;
			$n++;
		}
		return $id;
	}

	/** Persist a full ordered list of types. Returns the normalized stored list. */
	public static function save( $list ) {
		if ( ! is_array( $list ) ) {
			return self::all();
		}
		$store = array();
		$ids   = array();
		$order = 0;
		foreach ( $list as $t ) {
			$n = self::normalize( $t );
			if ( ! $n ) {
				continue;
			}
			if ( '' === $n['id'] || in_array( $n['id'], $ids, true ) ) {
				$n['id'] = self::slugify( $n['label'] ?: 'type', $ids );
			}
			$n['order'] = $order++;
			$ids[]      = $n['id'];
			$store[]    = $n;
		}
		if ( empty( $store ) ) {
			$store[] = self::default_type();
		}
		update_option( self::OPTION, $store );
		return self::all();
	}

	/** Seed the option from the legacy settings if it has never been written. */
	public static function seed_if_empty() {
		if ( null === get_option( self::OPTION, null ) ) {
			update_option( self::OPTION, array( self::default_type() ) );
		}
	}
}
