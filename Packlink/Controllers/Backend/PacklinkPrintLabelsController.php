<?php

use iio\libmergepdf\Merger;
use Packlink\Controllers\Backend\PacklinkOrderDetailsController;
use Packlink\Utilities\Response;

class Shopware_Controllers_Backend_PacklinkPrintLabelsController extends PacklinkOrderDetailsController
{
    /**
     * Prints labels.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \iio\libmergepdf\Exception
     */
    public function printAction()
    {
        $orderQuery = $this->Request()->getQuery('orderIds');
        if (!empty($orderQuery) && !empty($orderIds = explode(',', $orderQuery))) {
            $pdfs = [];

            $orderService = $this->getOrderService();

            foreach ($orderIds as $id) {
                if (($dts = $this->getOrderShipmentDetailsService()->getDetailsByOrderId((int)$id)) !== null
                    && $orderService->isReadyToFetchShipmentLabels($dts->getStatus())
                ) {
                    $labels = $dts->getShipmentLabels();

                    if (empty($labels)) {
                        $labels = $orderService->getShipmentLabels($dts->getReference());
                        $dts->setShipmentLabels($labels);
                    }

                    /** @var \Packlink\BusinessLogic\Http\DTO\ShipmentLabel $label */
                    foreach ($labels as $label) {
                        $label->setPrinted(true);
                        if ($label->getLink() && $file = $this->downloadPdf($label->getLink())) {
                            $pdfs[] = $file;
                        }
                    }

                    $this->getOrderDetailsRepository()->update($dts);
                }
            }

            if (!empty($pdfs) && $pdf = $this->merge($pdfs)) {
                Response::inlineFile($pdf, 'application/pdf');
            }

            echo '<script>window.close()</script>';
            exit;
        }
    }

    /**
     * Downloads pdf.
     *
     * @param string $link
     *
     * @return bool | string
     */
    protected function downloadPdf($link)
    {
        if (($data = file_get_contents($link)) === false) {
            return $data;
        }

        $file = tempnam(sys_get_temp_dir(), 'packlink_pdf');
        file_put_contents($file, $data);

        return $file;
    }

    /**
     * Creates merged pdf.
     *
     * @param array $pdfs
     *
     * @return bool | string
     * @throws \iio\libmergepdf\Exception
     */
    protected function merge(array $pdfs)
    {
        $merger = new Merger();
        $merger->addIterator($pdfs);
        $data = $merger->merge();

        $file = tempnam(sys_get_temp_dir(), 'packlink_out_pdf');

        file_put_contents($file, $data);

        return $file;
    }
}