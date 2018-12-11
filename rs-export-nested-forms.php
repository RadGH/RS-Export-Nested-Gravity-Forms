<?php
/*
Plugin Name: RS Export Nested Forms
Description: Export field and values for nested forms, instead of just a list of entry IDs.
Author: Radley Sustaire
Version: 1.0.0
*/

if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Returns a form's data, keeps the result in memory for subsequent requests
 *
 * @param $form_id
 *
 * @return mixed
 */
function rs_enf_get_form_and_cache( $form_id ) {
	static $forms = null;
	if ( $forms === null ) $forms = array();
	
	if ( !isset($forms[$form_id]) ) {
		$forms[$form_id] = GFAPI::get_form( $form_id );
	}
	
	return $forms[$form_id];
}


/**
 * Returns the value for a single entry from a nested form field
 *
 * @param $entry_id
 * @param $field_ids
 * @param $form
 *
 * @return string
 */
function rs_enf_get_nested_field_value( $entry_id, $field_ids, $form ) {
	$entry = GFAPI::get_entry( $entry_id );
	
	$fields = array();
	
	foreach( $field_ids as $field_id ) {
		$field_id_int = (int) $field_id;
		
		// Get the field label
		$label = $field_id;
		foreach( $form['fields'] as $f ) {
			if ( $f['id'] === $field_id_int && $f['label'] ) {
				$label = $f['label'];
				break;
			}
		}
		
		// Get field value. $field_id is a string which works for key ["12.1"] but not for key [12], so then try integer
		$value = isset($entry[$field_id]) ? $entry[$field_id] : null;
		if ( $value === null && isset($entry[ $field_id_int ]) ) $value = $entry[ $field_id_int ];
		
		
		$fields[] = "$label:\n {$value}";
	}
	
	return implode("\n\n", $fields);
}


/**
 * Replace field value for the field type "form" (nested forms from Gravity Perks). The default value is a comma separated list of entry ID's. The new value is a string containing field names and value from every entry.
 * 
 * @param $value
 * @param $form_id
 * @param $field_id
 * @param $entry
 *
 * @return string
 */
function rs_enf_replace_nested_form_export_value( $value, $form_id, $field_id, $entry ) {
	// If empty, do nothing
	if ( empty($value) ) return $value;
	
	$form  = rs_enf_get_form_and_cache( $form_id );
	$field = RGFormsModel::get_field( $form, $field_id );
	
	// If not a nested form, do nothing
	if ( $field->type !== 'form' ) return $value;
	if ( !property_exists($field, 'gpnfForm') ) return $value;
	if ( empty($field->gpnfForm) ) return $value;
	
	$entries = array();
	
	$nested_form = rs_enf_get_form_and_cache( $field->gpnfForm );
	$nested_field_ids = (array) $field->gpnfFields;
	$ids = explode( ',', $value );
	
	$entry_count = count($ids);
	
	foreach( $ids as $i => $entry_id ) {
		$entries[$i] = rs_enf_get_nested_field_value( $entry_id, $nested_field_ids, $nested_form );
		
		if ( $i < $entry_count - 1 ) $entries[$i] .= "\n\n——";
	}
	
	
	$title = $nested_form['title'] . " (" . ( $entry_count != 1 ? $entry_count . ' entries' : '1 entry' ) . ')';
	
	return $title . "\n\n" . implode("\n\n", $entries);
}
add_filter( 'gform_export_field_value', 'rs_enf_replace_nested_form_export_value', 20, 4 );