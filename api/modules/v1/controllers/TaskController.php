<?php

namespace api\modules\v1\controllers;

use api\components\ApiResponse;
use api\modules\v1\controllers\base\ApiController;
use api\modules\v1\models\Asset;
use api\modules\v1\models\Task;
use api\modules\v1\services\TaskService;
use Yii;
use yii\db\Exception as DbException;
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

        $behaviors['verbs'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'create' => ['POST'],
                'download' => ['GET'],
                'create-pack' => ['POST'],
            ],
        ];

        return $behaviors;
    }

    /**
     * 创建批量图片处理任务
     *
     * POST /v1/task/create
     */
    public function actionCreate()
    {
        try {
            $params = Yii::$app->request->getBodyParams();

            if (empty($params)) {
                throw new BadRequestHttpException('Empty request body');
            }

            /**
             * 1️⃣ 校验 options
             */
            if (empty($params['options']) || !is_array($params['options'])) {
                throw new BadRequestHttpException('Invalid options');
            }

            /**
             * 2️⃣ 校验 asset_ids
             */
            if (empty($params['asset_ids']) || !is_array($params['asset_ids'])) {
                throw new BadRequestHttpException('asset_ids must be an array');
            }

            $assetIds = array_unique($params['asset_ids']);

            if (count($assetIds) === 0) {
                throw new BadRequestHttpException('asset_ids cannot be empty');
            }

            /**
             * 3️⃣ 查询 Asset
             */
            $assets = Asset::find()
                ->where(['id' => $assetIds])
                ->andWhere(['deleted_at' => null])
                ->all();

            if (count($assets) !== count($assetIds)) {
                throw new BadRequestHttpException('Some assets not found or deleted');
            }

            /**
             * 4️⃣ 过滤过期资源
             */
            $now = time();

            foreach ($assets as $asset) {
                if ($asset->expires_at !== null && $asset->expires_at < $now) {
                    throw new BadRequestHttpException("Asset expired: {$asset->id}");
                }

                if (!is_file($asset->storage_path)) {
                    throw new BadRequestHttpException("Asset file missing: {$asset->id}");
                }
            }

            /**
             * 5️⃣ 构建统一参数传入 Service
             */
            $result = TaskService::createBatchFromAssets(
                $assets,
                $params['options']
            );

            return ApiResponse::success($result, 'tasks created');

        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (DbException $e) {
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
     * GET /v1/task/download?id=xxx
     */
    public function actionDownload(string $id)
    {
        $task = Task::findOne($id);

        if (!$task) {
            throw new NotFoundHttpException('Task not found');
        }

        if ($task->status !== Task::STATUS_DONE) {
            throw new BadRequestHttpException('Task is not finished');
        }

        $result = json_decode($task->result, true);

        if (!$result || empty($result['output_path'])) {
            throw new BadRequestHttpException('Output file not found');
        }

        $filePath = $result['output_path'];

        if (!is_file($filePath)) {
            throw new NotFoundHttpException('File does not exist');
        }

        $downloadName = TaskService::buildDownloadFilename($task);

        Yii::$app->response->format = Response::FORMAT_RAW;

        return Yii::$app->response->sendFile(
            $filePath,
            $downloadName,
            ['inline' => false]
        );
    }

    /**
 * 创建打包任务
 *
 * POST /v1/task/create-pack
 */
    public function actionCreatePack()
    {
        $params = Yii::$app->request->getBodyParams();

        $sourceBatchId = $params['source_batch_id'] ?? null;

        if (!$sourceBatchId) {
            throw new BadRequestHttpException('source_batch_id required');
        }

        // 校验是否存在已完成任务
        $exists = Task::find()
            ->where([
                'batch_id' => $sourceBatchId,
                'type' => Task::TYPE_COMPRESS,
            ])
            ->exists();

        if (!$exists) {
            throw new BadRequestHttpException('Invalid source batch');
        }

        $batchId = uniqid('pack_');

        $task = new Task();
        $task->id = uniqid('task_');
        $task->batch_id = $batchId;
        $task->type = Task::TYPE_PACK;
        $task->status = Task::STATUS_PENDING;
        $task->options = json_encode([
            'source_batch_id' => $sourceBatchId,
        ]);
        $task->created_at = time();

        if (!$task->save()) {
            throw new HttpException(500, 'Create pack task failed');
        }

        return ApiResponse::success([
            'batch_id' => $batchId,
            'task_id' => $task->id,
        ]);
    }

}
