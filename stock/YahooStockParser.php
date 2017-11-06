<?php

/**
* Yahoo Stock Parser
*/
class YahooStockParser
{
	/**
	 * 
	 */
	public function scanAll($start_id=1, $end_id=9999)
	{
		for ($i=$start_id; $i <= $end_id; $i++) { 

			$stockID = sprintf("%04d", $i);
			// echo $stockID;exit;

            $data = $this->parseData($stockID, 'yahoo');
            // print_r($data);exit;

            if (!$data) {

            	echo "{$stockID} - Notfound\n";
            	continue;
            }

            $stockModel = new Stocks;

	        $stock = $stockModel->findOne($data['id']);

	        if (!$stock) {

	            /* Create */
	            $stockModel->id = $data['id'];
	            $stockModel->title = $data['title'];
	            $stockModel->industry = $data['industry'];
	            $stockModel->checked_at = time();

	            $result = $stockModel->save(false);

	            if (!$result) 
	                throw new Exception("Error on Creating", 500);
	                
	        } else {

	            /* Update */
	            $stock->title = $data['title'];
	            $stock->industry = $data['industry'];
	            $stock->checked_at = time();

	            $result = $stock->save(false);

	            if (!$result) 
	                throw new Exception("Error on Updating", 500);
	        }

	        echo "{$stockID} - OK - {$data['title']}({$data['id']})\n";
        }
	}

	/**
	 * 
	 */
	public function updateData()
	{
		// Get Stocks
		$stocks = Stocks::find()
						->asArray()
						->all();

		// Parse each stock then update
		foreach ($stocks as $key => $stock) {
			
			$stockID = $stock['id'];

			$data = $this->parseData($stockID);

			$stock = $data ? Stocks::findOne($stockID) : NULL;
			// print_r($stock);exit;

			if ($stock) {

				/* Update */
	            $stock->title = $data['title'];
	            $stock->industry = $data['industry'];
	            $stock->checked_at = time();

	            $result = $stock->save(false);

	            if ($result===false) 
	                throw new Exception("Error on Updating", 500);

	            echo "{$stockID} - {$result} - {$data['title']}({$data['id']})\n";

			} else {

				$this->checkDelete($stockID, 'parseData');

				echo "{$stockID} - Failed and deleted\n";
			}
		}
	}

	/**
	 * 
	 */
	public function updatePrice()
	{
		try {

			// Get Stocks
			$stocks = Stocks::find()
							->asArray()
							->all();

			// Parse each stock then update
			foreach ($stocks as $key => $stock) {

				$stockID = $stock['id'];

				$data = $this->parsePrice($stockID);
				// echo "{$stockID}\n";print_r($data);exit;

				$stock = ($data) ? Stocks::findOne($stockID) : NULL;
				// print_r($stock);exit;

				if ($data) {

					/* Update */
		            $stock->price = $data['price'];
		            $stock->price_at = time();

		            $result = $stock->save(false);

		            if ($result===false) 
		                throw new Exception("Error on Updating", 500);

		            $messgae = ($result) ? 'OK' : 'Error';

		            echo "{$stockID} - {$messgae} - {$data['price']}\n";

				} else {

					$this->checkDelete($stockID, 'parsePrice');

					echo "{$stockID} - Failed and deleted\n";
				}
			}
			
		} catch (Exception $e) {
			
		}
	}

	/**
	 * 
	 */
	public function updateDividends()
	{
		try {

			// Get Stocks
			$stocks = Stocks::find()
							->asArray()
							->all();

			// Parse each stock then update
			foreach ($stocks as $key => $stock) {

				$stockID = $stock['id'];

				// $stockID = 1460;
				$data = $this->parseDividends($stockID);
				// echo "{$stockID}\n";print_r($data);exit;

				$stock = $data ? Stocks::findOne($stockID) : NULL;
				// print_r($stock);exit;

				if ($stock) {

					/* Update */
		            $stock->dividend_cash = $data['cash'];
		            $stock->dividend_stock = $data['stock'];
		            $stock->dividend_at = time();

		            $result = $stock->save(false);

		            if ($result===false) 
		                throw new Exception("Error on Updating", 500);

		            echo "{$stockID} - {$result} - {$data['cash']}|{$data['stock']}\n";

				} else {

					echo "{$stockID} - Failed\n";
				}
			}
			
		} catch (Exception $e) {
			
		}
	}

	/**
	 * 
	 */
	public function calculate()
	{
		try {

			// Get Stocks
			$stocks = Stocks::find()
							->asArray()
							->all();

			// Parse each stock then update
			foreach ($stocks as $key => $stock) {

				$stock = $stock ? Stocks::findOne($stock['id']) : NULL;
				// print_r($stock);exit;

				if ($stock) {

					$dividendYield = ($stock['dividend_cash'] && $stock['price']>0) ? $stock['dividend_cash'] / $stock['price'] : 0;
					$dividendYield = number_format($dividendYield, 2);

					$ROE = $stock['dividend_stock'] ? $dividendYield + number_format($stock['dividend_stock'] / 10, 2) : $dividendYield;

					/* Update */
		            $stock->dividend_yield = $dividendYield;
		            $stock->roe = $ROE;
		            $stock->calculated_at = time();

		            $result = $stock->save(false);

		            if ($result===false) 
		                throw new Exception("Error on Updating", 500);

		            echo "{$stock['id']} - {$result} - {$dividendYield}|{$ROE}\n";

				} else {

					echo "{$stock['id']} - Failed\n";
				}
			}
			
		} catch (Exception $e) {
			
		}
	}

	private function parseData($stockID, $source='yahoo')
    {
        switch ($source) {
        	
        	case 'yahoo':
        		
        		/* Fetch HTML from MOPS official website */
		        $url = "https://tw.stock.yahoo.com/d/s/company_{$stockID}.html";
		        $html = @file_get_contents($url);
		        // echo $html;exit;
		        
		        # Check for HTML
		        if (strlen($html)<1000)
		            return false;

		        // Big5 to UTF8
		        $html = mb_convert_encoding($html, "UTF-8", "BIG5");
		        // $html = iconv("big5","UTF-8",$html); 
		        // echo $html;exit;

		        /**
		         * Parse
		         */
		        $data = [];
		        $result = '';

		        /* Get ID */
		        $pattern = "/<input type=text name=\"stock_id\" size=\"5\" value=\"(\d+)\">/i";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['id'] = $result;

		        /* Get Title */
		        $pattern = "/<TITLE>([\x{4e00}-\x{9fa5}]+)/u";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['title'] = $result;

		        /* Get Industry */
		        $pattern = "/<td width=\"84\" align=\"left\">([\x{4e00}-\x{9fa5}]+)/u";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : ''; 
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['industry'] = $result;

        		break;
        	
        	
        	case 'mops':

        		/* Fetch HTML from MOPS official website */
		        $url = "http://mops.twse.com.tw/mops/web/ajax_t05st03?TYPEK=all&firstin=1&queryName=co_id&co_id={$stockID}";
		        $html = @file_get_contents($url);
		        // echo $html;exit;
		        
		        # Check for HTML
		        if (strlen($html)<1000)
		            return false;

		        /**
		         * Parse
		         */
		        $data = [];
		        $result = '';

		        /* Get ID */
		        $pattern = "/股票代號<\/th><td class='lColor'>(\d+)<\/td>/i";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['id'] = $result;

		        /* Get Title */
		        $pattern = "/([\x{4e00}-\x{9fa5}]+)<\/span>　公司提供/u";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['title'] = $result;

		        /* Get Industry */
		        $pattern = "/<td nowrap class='lColor'>([\x{4e00}-\x{9fa5}]+)/u";
		        $result = preg_match($pattern, $html, $match) ? $match[1] : ''; 
		        // print_r($match);exit;
		        // echo $result;exit;
		        $data['industry'] = $result;

        	default:
        		# code...
        		break;
        }

        return $data;
    }

    private function parsePrice($stockID)
    {
        /* Fetch HTML from MOPS official website */
        $url = "https://tw.stock.yahoo.com/q/q?s={$stockID}";
        $html = @file_get_contents($url);
        // echo $html;exit;
        
        # Check for HTML
        if (strlen($html)<1000)
            return false;

        /**
         * Parse
         */
        $data = [];
        $result = '';

        /* Get Price */
        $pattern = "/<td align=\"center\" bgcolor=\"#FFFfff\" nowrap><b>(\d+\.\d+)/i";
        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
        // print_r($match);exit;
        // echo $result;exit;
        $data['price'] = $result;

        return $data;
    }

    /**
	 * 
	 */
	public function parseDividends($stockID)
	{
		/* Fetch HTML from MOPS official website */
        $url = "https://tw.stock.yahoo.com/d/s/company_{$stockID}.html";
        $html = @file_get_contents($url);
        // echo $html;exit;
        
        # Check for HTML
        if (strlen($html)<1000)
            return false;

        /**
         * Parse
         */
        $data = [];
        $result = '';

        /* Get Dividend-Cash */
        $pattern = "/<td width=\"83\" align=\"center\">(\d+\.\d+|-)/i";
        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
        // print_r($match);exit;
        // echo $result;exit;
        $result = is_numeric($result) ? $result : 0;
        $data['cash'] = $result;

        /* Get Dividend-Stock (First) */
        $pattern = "/<td align=\"center\">(\d+\.\d+|-)/i";
        $result = preg_match($pattern, $html, $match) ? $match[1] : '';
        // print_r($match);exit;
        // echo $result;exit;
        $result = is_numeric($result) ? $result : 0;
        $data['stock'] = $result;

        return $data;
	}

	private function checkDelete($stockID, $parser='parseData', $times=3, $sleepSec=3)
	{
		if (method_exists($this, $parser)) {
			
			// Check
			for ($i=0; $i < $times; $i++) { 
				
				$data = $this->$parser();

				if ($data) {
					
					return;
				}

				sleep($sleepSec);
			}

			// Delete
			$result = Stocks::findOne($stockID)->delete();
		}
	}
}
