<?php
/**
 * A statement that has been through prepare().
 *
 * Nothing else can make one, so holding one is proof of preparation. The
 * SQL inside is read only by Db, which is the only thing that runs it.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Support;

defined( 'ABSPATH' ) || exit;

final class Statement {

	/** @var string */
	private $sql;

	/**
	 * @param string $sql The prepared SQL. Only Db::prepare() calls this.
	 */
	public function __construct( string $sql ) {
		$this->sql = $sql;
	}

	/** The prepared SQL. */
	public function sql(): string {
		return $this->sql;
	}
}
