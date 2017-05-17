<?namespace Intervolga\Migrato\Tool\Console\Command;

use Symfony\Component\Console\Input\ArrayInput;

class ValidateComplex extends BaseCommand
{
	protected function configure()
	{
		$this->setName('validatecomplex');
		$this->setDescription('validatecomplex');
	}

	public function executeInner()
	{
		$command = $this->getApplication()->find('validate');
		$command->run(new ArrayInput(array()), $this->output);
	}
}