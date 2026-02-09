<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use api\modules\v1\models\Task;
use api\modules\v1\services\TaskService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

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
                'download' => ['GET'],
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

    /**
     * 下载任务产物（单张图片）
     *
     * GET /v1/task/download?id=task_xxx
     */
    public function actionDownload(string $id)
    {
        /** @var Task|null $task */
        $task = Task::findOne($id);

        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        if ($task->status !== 'done') {
            throw new BadRequestHttpException('Task is not finished');
        }

        if (empty($task->output_path)) {
            throw new BadRequestHttpException('Output file not found');
        }

        $filePath = $task->output_path;

        if (!is_file($filePath)) {
            throw new NotFoundHttpException('File does not exist');
        }

        // ✅ 下载文件名（可定制）
        $downloadName = TaskService::buildDownloadFilename($task);

        // ⚠️ 禁用 Yii 的 response formatter
        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $filePath,
            $downloadName,
            [
                'inline' => false,
            ]
        );
    }
}
