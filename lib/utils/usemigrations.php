<?php

namespace Intervolga\Migrato\Utils;

#[\Attribute(\Attribute::TARGET_CLASS)]
class UseMigrations
{
	public function __construct()
	{
	}
}