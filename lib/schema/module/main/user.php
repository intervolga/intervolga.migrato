<?namespace Intervolga\Migrato\Schema\Module\Main;

use Intervolga\Migrato\Schema\BaseSchema;

class User extends BaseSchema
{
	public function __construct()
	{
		$this->ufObjectName = "USER";
	}
}