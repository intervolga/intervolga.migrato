<?namespace Intervolga\Migrato\Tool\Console;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

class Output extends ConsoleOutput
{
	public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
	{
		parent::__construct($verbosity, $decorated, new Formatter());
		$this->initStyles();
	}

	public function setWindowsCharset($isWindowsCharset = true)
	{
		$formatter = $this->getFormatter();
		if ($formatter instanceof Formatter)
		{
			$formatter->setWindowsCharset($isWindowsCharset);
		}
	}

	protected function initStyles()
	{
		$style = new OutputFormatterStyle('blue', null, array('bold', 'blink'));
		$this->getFormatter()->setStyle('info', $style);

		$style = new OutputFormatterStyle('green');
		$this->getFormatter()->setStyle('ok', $style);

		$style = new OutputFormatterStyle('red');
		$this->getFormatter()->setStyle('fail', $style);
	}
}