<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use api\modules\v1\services\TaskService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;

class TaskController extends ApiController
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // 限制 HTTP 请求方法
        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                // actionCreate 仅允许 POST
                'create' => ['POST'],
            ],
        ];

        return $behaviors;
    }

    /**
     * 创建任务
     *
     * POST /v1/task/create
     */
    public function actionCreate()
    {
        try {
            /**
             * 1️⃣ 获取请求参数（支持 JSON / form-data）
             */
            $params = Yii::$app->request->getBodyParams();

            if (empty($params)) {
                throw new BadRequestHttpException('Empty request body');
            }

            /**
             * 2️⃣ 调用 Service
             */
            $result = TaskService::createBatch($params);

            /**
             * 3️⃣ 统一成功返回
             */
            // return [
            //     'code' => 0,
            //     'message' => 'ok',
            //     'data' => $result,
            // ];

            return ApiResponse::success($result, 'tasks created');

        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), 'task.create');

            throw new HttpException(500, 'Database error');
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), 'task.create');

            throw new HttpException(400, $e->getMessage());
        }
    }
}
