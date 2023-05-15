<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMAIL</title>
    <!-- import cdn for bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
</head>
<body>
    <div style="width: 100%; height: 100%; background-color: #f5f5f5; padding: 20px; box-sizing: border-box;">
        <div style="text-align: end;">
            <span>SUPPLIES EXPENSE NO. <?= $purchase['id'] ?></span>
        </div>
    
        <div style="margin-top: 20px; width: 100%; margin-bottom: 20px; background-color: #fff; padding: 20px; box-sizing: border-box;">
            <div style="text-align: center;">
                <img src="https://mangomagic.myt-pos.com/static/media/logo_2.33299458199ff7b38f7b.png"/>
            </div>
            <div style="text-align: center; margin-bottom: 20px;">
                MYT SOFTDEV SOLUTIONS.
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Supplier:</div>
                    <div><?= $supplier['trade_name'] ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Branch:</div>
                    <div><?= $branch['name'] ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Forwarder:</div>
                    <div><?= $purchase['forwarder_name'] ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Type:</div>
                    <div><?= $purchase['expense_name'] ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Delivery Address:</div>
                    <div><?= $purchase['delivery_address'] ?></div>
                </div>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Purchase Date:</div>
                    <div><?= DATE('M d, Y', strtotime($purchase['supplies_expense_date'])) ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Delivery Date:</div>
                    <div><?= DATE('M d, Y', strtotime($purchase['delivery_date'])) ?></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                    <div style="font-weight: bold;">Requisitioner:</div>
                    <div><?= $purchase['requisitioner_name'] ?></div>
                </div>
            </div>

            <div style="margin-top: 20px; margin-bottom: 20px; background-color: #fff; padding: 20px; box-sizing: border-box;">
                <span> <?= $purchase['remarks'] ?> </span>
            </div>
            <div style="margin-top: 20px; margin-bottom: 20px; background-color: #fff; padding: 20px; box-sizing: border-box;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #000;">
                    <thead>
                        <tr>
                            <th scope="col">Item</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Unit</th>
                            <th scope="col">Price</th>
                            <th scope="col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchase_items as $item) : ?>
                            <tr style="border-top: 1px solid #000;">
                                <td style="border: 1px solid black;"><?= $item['name'] ?></td>
                                <td style="border: 1px solid black;"><?= $item['qty'] ?></td>
                                <td style="border: 1px solid black;"><?= $item['unit'] ?></td>
                                <td style="border: 1px solid black;"><?= $item['price'] ?></td>
                                <td style="border: 1px solid black;"><?= $item['total'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="border-top: 1px solid #000;">
                            <td colspan="4" style="border: 1px solid black; text-align: right;">Total Amount</td>
                            <td style="border: 1px solid black;"><?= $purchase['grand_total'] ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 20px; margin-bottom: 20px; background-color: #fff; box-sizing: border-box;">
                <div style="font-weight: bold;" style='color:"#169422";font-family:"Gotham-Rounded-Medium";'> Additional Note: </div>
                <p><? $purchase['remarks'] ?></p>
            </div>
            <div style="margin:auto; width: 100%; display: flex; justify-content: space-between; margin-top: 10px; font-weight: bold;">
                <div style="text-align: center;">
                    <img src="https://i.ibb.co/nngr1WH/signature.png" style="height: 50%; margin-bottom: -50px;z-index: 100;"/>
                    <p style="text-align: center; font-weight: bold; text-transform: uppercase"><?=  $purchase['approved_by_name'] ?></p>
                    <p>Approved by</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>